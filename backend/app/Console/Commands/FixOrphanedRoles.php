<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Staff;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time repair for a historical bug (see RoleService::create()'s own
 * comment) where custom roles created through the API never had tenant_id
 * resolved, so they were saved with tenant_id = NULL. TenantScope treats a
 * NULL tenant_id as "shared across every tenant" (by design, for genuine
 * is_system templates — see TenantScopeTest), so these orphaned custom
 * roles ended up silently shared by whichever tenants happened to assign
 * staff to them, and assertRoleAssignable() now correctly refuses to
 * assign them further since they were never legitimately shared.
 *
 * For each orphaned role (tenant_id NULL, is_system false):
 *   - referenced by staff from exactly one tenant  -> backfill tenant_id.
 *   - referenced by staff from multiple tenants    -> clone one real row
 *     per tenant (name/description/permissions preserved), repoint each
 *     tenant's own staff at their own clone, then delete the original.
 *   - referenced by no staff at all                -> left untouched for
 *     manual review; nothing to safely infer a tenant from.
 */
class FixOrphanedRoles extends Command
{
    protected $signature = 'roles:fix-orphaned {--dry-run}';

    protected $description = 'Backfill or clone roles left with a NULL tenant_id by a historical bug, so tenants stop accidentally sharing one role row.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $orphaned = Role::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where('is_system', false)
            ->get();

        if ($orphaned->isEmpty()) {
            $this->info('No orphaned roles found.');

            return self::SUCCESS;
        }

        foreach ($orphaned as $role) {
            $this->fixRole($role, $dryRun);
        }

        return self::SUCCESS;
    }

    private function fixRole(Role $role, bool $dryRun): void
    {
        $tenantIds = Staff::withoutGlobalScopes()
            ->where('role_id', $role->id)
            ->pluck('tenant_id')
            ->unique()
            ->values();

        if ($tenantIds->isEmpty()) {
            $this->warn("Role '{$role->name}' ({$role->id}) has no staff referencing it — left untouched, review manually.");

            return;
        }

        if ($tenantIds->count() === 1) {
            $this->info("Role '{$role->name}' ({$role->id}) belongs to exactly one tenant — backfilling tenant_id.");

            if (! $dryRun) {
                $role->update(['tenant_id' => $tenantIds->first()]);
            }

            return;
        }

        $this->warn("Role '{$role->name}' ({$role->id}) is shared by {$tenantIds->count()} tenants — cloning one row per tenant.");

        if ($dryRun) {
            foreach ($tenantIds as $tenantId) {
                $this->line("  would clone for tenant {$tenantId}");
            }

            return;
        }

        $permissionIds = $role->permissions()->pluck('permissions.id');

        DB::transaction(function () use ($role, $tenantIds, $permissionIds) {
            foreach ($tenantIds as $tenantId) {
                $clone = Role::create([
                    'tenant_id' => $tenantId,
                    'name' => $role->name,
                    'description' => $role->description,
                    'is_system' => false,
                ]);
                $clone->permissions()->sync($permissionIds);

                $affectedStaff = Staff::withoutGlobalScopes()
                    ->where('role_id', $role->id)
                    ->where('tenant_id', $tenantId)
                    ->get();

                Staff::withoutGlobalScopes()
                    ->whereIn('id', $affectedStaff->pluck('id'))
                    ->update(['role_id' => $clone->id]);

                $affectedStaff->each(fn (Staff $staff) => $staff->user?->clearPermissionCache());

                $this->line("  cloned -> {$clone->id} for tenant {$tenantId} ({$affectedStaff->count()} staff repointed)");
            }

            if (! Staff::withoutGlobalScopes()->where('role_id', $role->id)->exists()) {
                $role->delete();
                $this->line("  deleted original orphaned role {$role->id}");
            }
        });
    }
}
