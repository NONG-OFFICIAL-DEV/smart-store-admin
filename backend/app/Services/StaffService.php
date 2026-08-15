<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Repositories\Contracts\StaffRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StaffService extends BaseService
{
    public function __construct(
        StaffRepositoryInterface $repository,
        private TenantResolver $tenantResolver,
        private PasswordService $passwordService
    ) {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function byBranch(Branch $branch, array $filters): LengthAwarePaginator
    {
        return $this->list(array_merge($filters, ['branch_id' => $branch->id]));
    }

    public function create(array $data, Request $request): Staff
    {
        $tenantId = $this->tenantResolver->resolve($request);

        $this->assertRoleAssignable($data['role_id'], $tenantId);

        return DB::transaction(function () use ($data, $tenantId) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
            ]);

            // Admin-set passwords for new staff are always temporary —
            // the staff member must change it on first login.
            $this->passwordService->applyPassword($user, $data['password'], temporary: true);

            $staffData = [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'branch_id' => $data['branch_id'],
                'role_id' => $data['role_id'],
                'hire_date' => $data['hire_date'] ?? null,
                'hourly_rate' => $data['hourly_rate'] ?? null,
                'salary' => $data['salary'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ];

            if (! empty($data['pin_code'])) {
                $this->assertPinUnique($data['pin_code'], $data['branch_id']);
                $staffData['pin_code'] = Hash::make($data['pin_code']);
            }

            $staffData['employee_code'] = Staff::generateEmployeeCode($tenantId);

            $staff = $this->repository->create($staffData);

            return $staff->load(['user', 'role', 'branch']);
        });
    }

    public function update(Staff $staff, array $validated): Staff
    {
        if (array_key_exists('role_id', $validated)) {
            $this->assertRoleAssignable($validated['role_id'], $staff->tenant_id);
        }

        if (array_key_exists('pin_code', $validated)) {
            if (! is_null($validated['pin_code'])) {
                $branchId = $validated['branch_id'] ?? $staff->branch_id;
                $this->assertPinUnique($validated['pin_code'], $branchId, $staff->id);
                $validated['pin_code'] = Hash::make($validated['pin_code']);
            }
        }

        $staff = $this->repository->update($staff, $validated);

        // Role change means a different permission set — bust the cached
        // one (User::getAllPermissions(), 5 min TTL) so it applies immediately.
        if (array_key_exists('role_id', $validated)) {
            $staff->user?->clearPermissionCache();
        }

        return $staff->load(['user', 'role', 'branch']);
    }

    public function deactivate(Staff $staff): Staff
    {
        // Soft-disable only — staff have orders/shifts/payments linked, never hard delete.
        return $this->repository->update($staff, ['is_active' => false]);
    }

    public function removePin(Staff $staff): Staff
    {
        return $this->repository->update($staff, ['pin_code' => null]);
    }

    // Admin generates (or the caller can supply) a new temporary password
    // for a staff member's linked user account.
    public function resetPassword(Staff $staff): string
    {
        $staff->loadMissing('user');

        if (! $staff->user) {
            throw ValidationException::withMessages([
                'staff' => 'Staff has no linked user account.',
            ]);
        }

        return $this->passwordService->adminReset($staff->user);
    }

    // Staff role assignment must never be able to grant the protected Owner
    // role, and must never reach across into another tenant's roles.
    private function assertRoleAssignable(string $roleId, string $tenantId): void
    {
        $role = Role::withoutGlobalScopes()
            ->where('id', $roleId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $role) {
            throw ValidationException::withMessages([
                'role_id' => 'The selected role does not belong to this business.',
            ]);
        }

        if ($role->isOwnerRole()) {
            throw ValidationException::withMessages([
                'role_id' => 'Cannot assign the Owner role directly. Use ownership transfer instead.',
            ]);
        }
    }

    private function assertPinUnique(string $plainPin, string $branchId, ?string $excludeStaffId = null): void
    {
        $query = Staff::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereNotNull('pin_code');

        if ($excludeStaffId) {
            $query->where('id', '!=', $excludeStaffId);
        }

        foreach ($query->cursor() as $existing) {
            if (Hash::check($plainPin, $existing->pin_code)) {
                throw ValidationException::withMessages([
                    'pin_code' => 'This PIN is already in use by another staff member in this branch.',
                ]);
            }
        }
    }
}
