<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Super-admin scoped: view one tenant's users (owner + staff), deactivate/
 * reactivate a staff account, or trigger a password reset. Deliberately
 * narrow — no name/email/role editing, those stay the tenant's own Staff
 * management (StaffController) or the tenant owner's own business
 * decisions. Unlike a User.tenant_id-based model, tenancy here is indirect
 * (Tenant.owner_user_id for the owner, Staff.user_id/tenant_id for
 * everyone else), so every lookup here has to resolve both shapes rather
 * than a single `where('tenant_id', ...)`.
 */
class AdminTenantUserService
{
    public function __construct(private PasswordService $passwords)
    {
    }

    public function usersFor(Tenant $tenant): Collection
    {
        $tenant->loadMissing('owner');
        $rows = collect();

        if ($tenant->owner) {
            $rows->push($this->ownerRow($tenant->owner));
        }

        Staff::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->with(['user', 'role', 'branch'])
            ->get()
            ->each(fn (Staff $staff) => $rows->push($this->staffRow($staff)));

        return $rows->values();
    }

    public function deactivate(Tenant $tenant, User $user): array
    {
        $staff = $this->resolveStaffLink($tenant, $user);

        if (! $staff) {
            throw ValidationException::withMessages([
                'user' => 'Cannot deactivate the tenant owner. Transfer ownership first.',
            ]);
        }

        $staff->update(['is_active' => false]);

        $this->logAdminAction($tenant, 'admin.user_deactivated', $user->id, "Super admin deactivated {$user->full_name} ({$tenant->name})");

        return $this->staffRow($staff->fresh(['user', 'role', 'branch']));
    }

    public function reactivate(Tenant $tenant, User $user): array
    {
        $staff = $this->resolveStaffLink($tenant, $user);

        if (! $staff) {
            throw ValidationException::withMessages([
                'user' => 'The owner is never deactivated, so there is nothing to reactivate.',
            ]);
        }

        $staff->update(['is_active' => true]);

        $this->logAdminAction($tenant, 'admin.user_reactivated', $user->id, "Super admin reactivated {$user->full_name} ({$tenant->name})");

        return $this->staffRow($staff->fresh(['user', 'role', 'branch']));
    }

    public function resetPassword(Tenant $tenant, User $user): string
    {
        $this->assertBelongsToTenant($tenant, $user);

        $temporaryPassword = $this->passwords->adminReset($user);

        $this->logAdminAction($tenant, 'admin.user_password_reset', $user->id, "Super admin reset {$user->full_name}'s password ({$tenant->name})");

        return $temporaryPassword;
    }

    /**
     * Issues a real access token for the tenant's owner — no refresh token,
     * so the impersonation session just expires with the access token's
     * normal TTL rather than being renewable forever.
     */
    public function impersonate(Tenant $tenant): array
    {
        $tenant->loadMissing('owner');

        if (! $tenant->owner) {
            throw ValidationException::withMessages([
                'tenant' => 'This tenant has no owner account to impersonate.',
            ]);
        }

        $accessToken = JWTAuth::fromUser($tenant->owner);

        $this->logAdminAction($tenant, 'admin.impersonation_started', $tenant->owner->id, "Super admin impersonated {$tenant->owner->full_name} (owner of {$tenant->name})");

        return [
            'access_token' => $accessToken,
            'owner' => $this->ownerRow($tenant->owner),
        ];
    }

    /**
     * Hand-built (not via ActivityLog::log()) because that helper infers
     * tenant_id from the ACTING user, which is null for a super admin —
     * every row here needs the AFFECTED tenant's real id so it shows up
     * in that tenant's own activity trail, not as a null/shared-tenant row.
     */
    protected function logAdminAction(Tenant $tenant, string $action, string $entityId, string $description): void
    {
        $admin = auth()->user();

        ActivityLog::create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin?->id,
            'user_name' => $admin ? trim($admin->first_name.' '.$admin->last_name) : null,
            'user_email' => $admin?->email,
            'action' => $action,
            'entity_type' => 'User',
            'entity_id' => $entityId,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Returns the Staff row if $user is staff-linked to $tenant, null if
     * $user IS the tenant's owner, or throws 404 if $user belongs to
     * neither — admin routes carry no tenant-scoping middleware, so a
     * {tenant}/{user} route-param mismatch isn't caught anywhere else.
     */
    protected function resolveStaffLink(Tenant $tenant, User $user): ?Staff
    {
        if ($tenant->owner_user_id === $user->id) {
            return null;
        }

        $staff = Staff::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $staff) {
            throw new NotFoundHttpException;
        }

        return $staff;
    }

    protected function assertBelongsToTenant(Tenant $tenant, User $user): void
    {
        if ($tenant->owner_user_id === $user->id) {
            return;
        }

        $isStaff = Staff::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->exists();

        if (! $isStaff) {
            throw new NotFoundHttpException;
        }
    }

    protected function ownerRow(User $owner): array
    {
        return [
            'type' => 'owner',
            'user_id' => $owner->id,
            'staff_id' => null,
            'full_name' => $owner->full_name,
            'email' => $owner->email,
            'phone' => $owner->phone,
            'role_name' => 'Owner',
            'branch_name' => null,
            'is_active' => $owner->is_active,
            'last_login_at' => $owner->last_login_at,
        ];
    }

    protected function staffRow(Staff $staff): array
    {
        return [
            'type' => 'staff',
            'user_id' => $staff->user_id,
            'staff_id' => $staff->id,
            'full_name' => $staff->user?->full_name,
            'email' => $staff->user?->email,
            'phone' => $staff->user?->phone,
            'role_name' => $staff->role?->name,
            'branch_name' => $staff->branch?->name,
            'is_active' => $staff->is_active,
            'last_login_at' => $staff->user?->last_login_at,
        ];
    }
}
