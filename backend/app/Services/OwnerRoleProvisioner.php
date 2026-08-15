<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;

class OwnerRoleProvisioner
{
    /**
     * Ensure the tenant has a protected 'Owner' role holding every permission
     * currently in the catalog, and return it. Idempotent — safe to call on
     * every tenant creation and from the backfill migration.
     */
    public function ensureFor(Tenant $tenant): Role
    {
        // withoutGlobalScopes: this provisioner deliberately operates across
        // tenant boundaries (it IS the tenant-scoping authority for the
        // Owner role), so it must not be filtered by the caller's own tenant.
        $role = Role::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => Role::OWNER_CODE],
            ['name' => 'Owner', 'description' => 'Full access to this business — automatically assigned to the tenant owner.', 'is_system' => true]
        );

        $this->syncAllPermissions($role);

        return $role;
    }

    // Re-syncs a single Owner role's permissions to the full current catalog.
    public function syncAllPermissions(Role $role): void
    {
        $role->permissions()->sync(Permission::pluck('id'));
        $role->clearStaffPermissionCache();
    }

    // Called whenever a new Permission is added to the catalog — every
    // tenant's Owner role must keep 100% of permissions automatically.
    public function attachPermissionToAllOwnerRoles(Permission $permission): void
    {
        Role::withoutGlobalScopes()
            ->where('code', Role::OWNER_CODE)
            ->get()
            ->each(function (Role $role) use ($permission) {
                $role->permissions()->syncWithoutDetaching([$permission->id]);
                $role->clearStaffPermissionCache();
            });
    }
}
