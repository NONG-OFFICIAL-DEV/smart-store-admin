<?php

namespace App\Services;

use App\Exceptions\SystemRoleLockedException;
use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoleService extends BaseService
{
    public function __construct(
        RoleRepositoryInterface $repository,
        private TenantResolver $tenantResolver
    ) {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    /**
     * `is_system` is never accepted here — the old controller mass-assigned
     * it straight from client input with no gate, and `tenant_id` was never
     * resolved at all (routes/api.php never sent it either), so every
     * "custom" role created through this endpoint silently got tenant_id =
     * NULL — which TenantScope treats as "shared across every tenant," the
     * exact cross-tenant leak this whole migration exists to close. Roles
     * created here are always tenant-scoped, non-system.
     */
    public function create(array $data, Request $request): Role
    {
        $tenantId = $this->tenantResolver->resolve($request);

        $this->assertNameUnique($tenantId, $data['name']);

        $data['tenant_id'] = $tenantId;
        $data['is_system'] = false;

        return $this->repository->create($data);
    }

    public function update(Role $role, array $data): Role
    {
        $this->assertNotSystem($role);

        unset($data['is_system'], $data['tenant_id']);

        if (array_key_exists('name', $data) && $data['name'] !== $role->name) {
            $this->assertNameUnique($role->tenant_id, $data['name'], excludeRoleId: $role->id);
        }

        $permissionIds = $data['permission_ids'] ?? null;
        unset($data['permission_ids']);

        if (! empty($data)) {
            $role = $this->repository->update($role, $data);
        }

        if ($permissionIds !== null) {
            $role = $this->syncPermissions($role, $permissionIds);
        }

        return $role->load('permissions');
    }

    public function delete(Role $role): bool
    {
        // destroy() never checked is_system before — a real gap, since
        // update() already protects these; a shared system template
        // (visible to every tenant) could otherwise be deleted by any one
        // of them.
        $this->assertNotSystem($role);

        return $this->repository->delete($role);
    }

    public function syncPermissions(Role $role, array $permissionIds): Role
    {
        $this->assertNotSystem($role);

        $role->permissions()->sync($permissionIds);
        $role->clearStaffPermissionCache();

        return $role->load('permissions');
    }

    private function assertNotSystem(Role $role): void
    {
        if ($role->is_system) {
            throw new SystemRoleLockedException();
        }
    }

    // Case-insensitive, per-tenant — the DB's (tenant_id, code) unique index
    // only covers the internal `code` slug (owner, etc.), never `name`, so
    // nothing previously stopped a tenant from creating "Manager" and
    // "manager" (or the exact same name twice) as separate role rows.
    private function assertNameUnique(string $tenantId, string $name, ?string $excludeRoleId = null): void
    {
        $exists = Role::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->when($excludeRoleId, fn ($query) => $query->where('id', '!=', $excludeRoleId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'A role with this name already exists.',
            ]);
        }
    }
}
