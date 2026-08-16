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
 *   - referenced by no staff at all                -> promoted in a second
 *     pass (see promoteRemainingOrphans()): these are unused, so nothing
 *     depends on them staying tenant-private — they're exactly the shape
 *     of a shared starter-role template (e.g. "Cashier", "Waiter/Server")
 *     that was created through the old buggy path instead of being
 *     properly marked is_system. Case-insensitive name duplicates among
 *     them (or against an existing real is_system template) are merged
 *     first, then the single survivor is promoted to is_system = true.
 */
class FixOrphanedRoles extends Command
{
    protected $signature = 'roles:fix-orphaned {--dry-run}';

    protected $description = 'Repair roles left with a NULL tenant_id by a historical bug: backfill/clone ones staff depend on, merge duplicates and promote the rest to shared system templates.';

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

        $this->promoteRemainingOrphans($dryRun);

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
            $this->line("Role '{$role->name}' ({$role->id}) has no staff referencing it — will be considered for template promotion.");

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

    /**
     * Whatever is left after fixRole() (tenant_id NULL, is_system false, no
     * staff at all) is safe to turn into a real shared template — nothing
     * depends on it staying tenant-private. Case-insensitive name
     * duplicates are merged first (permissions unioned into one survivor),
     * and if a real is_system template with the same name already exists,
     * the orphan is merged into THAT instead of creating a second one.
     */
    private function promoteRemainingOrphans(bool $dryRun): void
    {
        $remaining = Role::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where('is_system', false)
            ->get()
            ->groupBy(fn (Role $role) => mb_strtolower(trim($role->name)));

        if ($remaining->isEmpty()) {
            return;
        }

        foreach ($remaining as $normalizedName => $group) {
            $existingTemplate = Role::withoutGlobalScopes()
                ->whereNull('tenant_id')
                ->where('is_system', true)
                ->get()
                ->first(fn (Role $r) => mb_strtolower(trim($r->name)) === $normalizedName);

            if ($existingTemplate) {
                $keeper = $existingTemplate;
                $duplicates = $group; // every orphan with this name merges into the real template
            } else {
                $keeper = $group->shift(); // first orphan becomes the new template
                $duplicates = $group;      // shift() mutated $group in place — whatever's left are the extras
            }

            if ($duplicates->isEmpty() && $keeper->is_system) {
                continue; // already a template with no orphaned duplicates to fold in
            }

            $this->warn("Promoting '{$keeper->name}' to a shared system template" . ($duplicates->isNotEmpty() ? " (merging {$duplicates->count()} duplicate row(s))" : '') . '.');

            if ($dryRun) {
                foreach ($duplicates as $dup) {
                    $this->line("  would merge duplicate {$dup->id} into {$keeper->id}");
                }
                if (! $existingTemplate) {
                    $this->line("  would set is_system = true on {$keeper->id}");
                }

                continue;
            }

            DB::transaction(function () use ($keeper, $duplicates, $existingTemplate) {
                $permissionIds = $keeper->permissions()->pluck('permissions.id')->all();

                foreach ($duplicates as $dup) {
                    $permissionIds = array_unique(array_merge($permissionIds, $dup->permissions()->pluck('permissions.id')->all()));
                    $dup->delete();
                    $this->line("  merged duplicate {$dup->id} into {$keeper->id}");
                }

                if (! $existingTemplate) {
                    $keeper->update(['is_system' => true]);
                }

                $keeper->permissions()->sync($permissionIds);
            });
        }
    }
}
