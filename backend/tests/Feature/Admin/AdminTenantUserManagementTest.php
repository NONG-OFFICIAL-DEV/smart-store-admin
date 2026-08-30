<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdminTenantUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Covers the super-admin "Manage Users" feature on the Tenant page —
 * unlike photo-studio-saas, this project's User has no tenant_id column,
 * so "this tenant's users" means the owner (Tenant.owner_user_id) plus
 * every Staff row's linked user, not a single where('tenant_id', ...).
 */
class AdminTenantUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithOwner(string $name): array
    {
        $owner = User::create([
            'email' => strtolower($name).'@example.test',
            'first_name' => $name,
            'last_name' => 'Owner',
            'is_super_admin' => false,
        ]);

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => strtolower($name).'-'.substr((string) $owner->id, 0, 8),
            'owner_user_id' => $owner->id,
        ]);

        return [$tenant, $owner];
    }

    private function makeStaff(Tenant $tenant, string $name): array
    {
        $user = User::create([
            'email' => strtolower($name).'@example.test',
            'first_name' => $name,
            'last_name' => 'Staff',
            'is_super_admin' => false,
        ]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Cashier']);

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => $name.' Branch',
            'address_line1' => '123 Main St',
            'city' => 'Phnom Penh',
        ]);

        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'employee_code' => Staff::generateEmployeeCode($tenant->id),
            'is_active' => true,
        ]);

        return [$staff, $user];
    }

    public function test_it_lists_the_owner_and_staff_for_the_given_tenant_only(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$staffA, $staffUserA] = $this->makeStaff($tenantA, 'StaffA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');
        $this->makeStaff($tenantB, 'StaffB');

        $rows = $this->app->make(AdminTenantUserService::class)->usersFor($tenantA);

        $this->assertCount(2, $rows);
        $this->assertTrue($rows->contains(fn ($r) => $r['user_id'] === $ownerA->id && $r['type'] === 'owner'));
        $this->assertTrue($rows->contains(fn ($r) => $r['user_id'] === $staffUserA->id && $r['type'] === 'staff'));
    }

    public function test_deactivating_a_staff_member_sets_is_active_false(): void
    {
        [$tenant] = $this->makeTenantWithOwner('Tenant');
        [$staff, $staffUser] = $this->makeStaff($tenant, 'Staff');

        $row = $this->app->make(AdminTenantUserService::class)->deactivate($tenant, $staffUser);

        $this->assertFalse($row['is_active']);
        $this->assertFalse($staff->fresh()->is_active);
    }

    public function test_deactivating_the_owner_is_rejected(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('Tenant');

        $this->expectException(ValidationException::class);
        $this->app->make(AdminTenantUserService::class)->deactivate($tenant, $owner);

        $this->assertTrue($owner->fresh()->is_active);
    }

    public function test_reactivating_a_deactivated_staff_member_sets_is_active_true(): void
    {
        [$tenant] = $this->makeTenantWithOwner('Tenant');
        [$staff, $staffUser] = $this->makeStaff($tenant, 'Staff');
        $staff->update(['is_active' => false]);

        $row = $this->app->make(AdminTenantUserService::class)->reactivate($tenant, $staffUser);

        $this->assertTrue($row['is_active']);
        $this->assertTrue($staff->fresh()->is_active);
    }

    public function test_resetting_the_owners_password_returns_a_temporary_password(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('Tenant');
        $originalHash = $owner->password_hash;

        $temporaryPassword = $this->app->make(AdminTenantUserService::class)->resetPassword($tenant, $owner);

        $this->assertNotEmpty($temporaryPassword);
        $this->assertNotSame($originalHash, $owner->fresh()->password_hash);
        $this->assertTrue($owner->fresh()->must_change_password);
    }

    public function test_resetting_a_staff_members_password_returns_a_temporary_password(): void
    {
        [$tenant] = $this->makeTenantWithOwner('Tenant');
        [, $staffUser] = $this->makeStaff($tenant, 'Staff');

        $temporaryPassword = $this->app->make(AdminTenantUserService::class)->resetPassword($tenant, $staffUser);

        $this->assertNotEmpty($temporaryPassword);
        $this->assertTrue($staffUser->fresh()->must_change_password);
    }

    public function test_acting_on_a_user_from_a_different_tenant_is_rejected(): void
    {
        [$tenantA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');
        [, $staffUserB] = $this->makeStaff($tenantB, 'StaffB');

        $service = $this->app->make(AdminTenantUserService::class);

        $this->expectException(NotFoundHttpException::class);
        $service->resetPassword($tenantA, $ownerB);
    }

    public function test_acting_on_a_staff_member_from_a_different_tenant_is_rejected(): void
    {
        [$tenantA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');
        [$staffB, $staffUserB] = $this->makeStaff($tenantB, 'StaffB');

        $service = $this->app->make(AdminTenantUserService::class);

        $this->expectException(NotFoundHttpException::class);
        $service->deactivate($tenantA, $staffUserB);

        $this->assertTrue($staffB->fresh()->is_active);
    }

    public function test_deactivate_reactivate_and_reset_password_each_log_an_activity_entry(): void
    {
        $admin = User::create([
            'email' => 'super3@example.test',
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'is_super_admin' => true,
        ]);
        Auth::guard('api')->login($admin);

        [$tenant] = $this->makeTenantWithOwner('Tenant');
        [$staff, $staffUser] = $this->makeStaff($tenant, 'Staff');

        $service = $this->app->make(AdminTenantUserService::class);
        $service->deactivate($tenant, $staffUser);
        $service->reactivate($tenant, $staffUser);
        $service->resetPassword($tenant, $staffUser);

        foreach (['admin.user_deactivated', 'admin.user_reactivated', 'admin.user_password_reset'] as $action) {
            $log = ActivityLog::withoutGlobalScopes()
                ->where('action', $action)
                ->where('entity_id', $staffUser->id)
                ->first();

            $this->assertNotNull($log, "Expected an activity log row for {$action}");
            $this->assertSame($tenant->id, $log->tenant_id);
            $this->assertSame($admin->id, $log->user_id);
        }
    }

    public function test_impersonating_a_tenant_returns_a_valid_token_for_its_owner_and_logs_it(): void
    {
        $admin = User::create([
            'email' => 'super@example.test',
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'is_super_admin' => true,
        ]);
        Auth::guard('api')->login($admin);

        [$tenant, $owner] = $this->makeTenantWithOwner('Tenant');

        $result = $this->app->make(AdminTenantUserService::class)->impersonate($tenant);

        $this->assertNotEmpty($result['access_token']);
        $authenticated = JWTAuth::setToken($result['access_token'])->authenticate();
        $this->assertSame($owner->id, $authenticated->id);

        $log = ActivityLog::withoutGlobalScopes()
            ->where('action', 'admin.impersonation_started')
            ->where('entity_id', $owner->id)
            ->first();
        $this->assertNotNull($log);
        $this->assertSame($tenant->id, $log->tenant_id);
        $this->assertSame($admin->id, $log->user_id);
    }

    public function test_impersonating_a_tenant_with_no_owner_is_rejected(): void
    {
        $admin = User::create([
            'email' => 'super2@example.test',
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'is_super_admin' => true,
        ]);
        Auth::guard('api')->login($admin);

        $orphanOwner = User::create([
            'email' => 'orphan@example.test',
            'first_name' => 'Orphan',
            'last_name' => 'Owner',
            'is_super_admin' => false,
        ]);
        $tenant = Tenant::create([
            'name' => 'Orphan Tenant',
            'slug' => 'orphan-tenant',
            'owner_user_id' => $orphanOwner->id,
        ]);
        $orphanOwner->delete();

        $this->expectException(ValidationException::class);
        $this->app->make(AdminTenantUserService::class)->impersonate($tenant->fresh());
    }
}
