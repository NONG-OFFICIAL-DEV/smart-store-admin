<?php

namespace Tests\Feature\Staff;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlanService;
use App\Services\StaffService;
use App\Services\TenantSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Covers the Staff resource's Repository/Service migration (see
 * .claude/skills/migrate-resource-to-repository) — the User+Staff
 * create transaction, PIN uniqueness/hashing, and soft-disable-on-destroy
 * are real business logic moved into StaffService, not just CRUD.
 */
class StaffServiceTest extends TestCase
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

    private function makeBranch(Tenant $tenant, string $name): Branch
    {
        return Branch::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'address_line1' => '123 Main St',
            'city' => 'Phnom Penh',
        ]);
    }

    private function makeRole(Tenant $tenant, string $name): Role
    {
        return Role::create(['tenant_id' => $tenant->id, 'name' => $name]);
    }

    private function assignPlanWithSeats(Tenant $tenant, User $owner, int $seats): void
    {
        $plan = $this->app->make(PlanService::class)->create([
            'name' => 'Seat Limited', 'code' => 'seat-limited-'.uniqid(),
            'price_usd' => 10, 'seats' => $seats, 'storage_gb' => 1,
            'billing_cycles' => [['label' => 'Monthly', 'months' => 1, 'discount_percent' => 0]],
        ]);

        $this->app->make(TenantSubscriptionService::class)
            ->changePlan($tenant, $plan->id, $plan->billingCycles->first()->id, $owner->id);
    }

    public function test_create_rejects_a_staff_member_beyond_the_plan_seat_limit(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $role = $this->makeRole($tenantA, 'Cashier');
        $this->assignPlanWithSeats($tenantA, $ownerA, 1);

        Auth::login($ownerA);
        $service = $this->app->make(StaffService::class);

        $service->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.test',
            'password' => 'secret123',
            'branch_id' => $branch->id,
            'role_id' => $role->id,
        ], new Request());

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('This plan is limited to 1 staff seats. Upgrade to add more.');

        $service->create([
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email' => 'john@example.test',
            'password' => 'secret123',
            'branch_id' => $branch->id,
            'role_id' => $role->id,
        ], new Request());
    }

    public function test_create_allows_staff_up_to_exactly_the_plan_seat_limit(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $role = $this->makeRole($tenantA, 'Cashier');
        $this->assignPlanWithSeats($tenantA, $ownerA, 2);

        Auth::login($ownerA);
        $service = $this->app->make(StaffService::class);

        $service->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.test',
            'password' => 'secret123',
            'branch_id' => $branch->id,
            'role_id' => $role->id,
        ], new Request());

        $staff = $service->create([
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email' => 'john@example.test',
            'password' => 'secret123',
            'branch_id' => $branch->id,
            'role_id' => $role->id,
        ], new Request());

        $this->assertNotNull($staff->id);
        $this->assertSame(2, Staff::where('tenant_id', $tenantA->id)->where('is_active', true)->count());
    }

    public function test_create_makes_a_user_and_staff_record_in_one_transaction(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $role = $this->makeRole($tenantA, 'Cashier');

        Auth::login($ownerA);
        $service = $this->app->make(StaffService::class);

        $staff = $service->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.test',
            'password' => 'secret123',
            'branch_id' => $branch->id,
            'role_id' => $role->id,
        ], new Request());

        $this->assertNotNull($staff->user_id);
        $this->assertSame('Jane', $staff->user->first_name);
        $this->assertSame($tenantA->id, $staff->tenant_id);
        $this->assertNotNull($staff->employee_code);
        $this->assertTrue(User::where('email', 'jane@example.test')->exists());

        // Went through PasswordService::applyPassword(..., temporary: true),
        // not a raw bcrypt() — must_change_password should be set.
        $this->assertTrue($staff->user->must_change_password);
        $this->assertNull($staff->user->password_changed_at);
    }

    public function test_cannot_assign_the_owner_role_to_staff(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $ownerRole = Role::create(['tenant_id' => $tenantA->id, 'name' => 'Owner', 'code' => Role::OWNER_CODE, 'is_system' => true]);

        Auth::login($ownerA);
        $service = $this->app->make(StaffService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $service->create([
            'first_name' => 'Sneaky', 'last_name' => 'Staff', 'email' => 'sneaky@example.test',
            'password' => 'secret123', 'branch_id' => $branch->id, 'role_id' => $ownerRole->id,
        ], new Request());
    }

    public function test_cannot_assign_a_role_belonging_to_another_tenant(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');
        $branch = $this->makeBranch($tenantA, 'Main');
        $roleB = $this->makeRole($tenantB, 'Cashier');

        Auth::login($ownerA);
        $service = $this->app->make(StaffService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $service->create([
            'first_name' => 'Cross', 'last_name' => 'Tenant', 'email' => 'cross@example.test',
            'password' => 'secret123', 'branch_id' => $branch->id, 'role_id' => $roleB->id,
        ], new Request());
    }

    public function test_a_shared_system_role_with_null_tenant_id_is_assignable(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $sharedRole = Role::create(['tenant_id' => null, 'name' => 'Shared Template', 'is_system' => true]);

        Auth::login($ownerA);
        $service = $this->app->make(StaffService::class);

        $staff = $service->create([
            'first_name' => 'Shared', 'last_name' => 'Role', 'email' => 'shared@example.test',
            'password' => 'secret123', 'branch_id' => $branch->id, 'role_id' => $sharedRole->id,
        ], new Request());

        $this->assertSame($sharedRole->id, $staff->role_id);
    }

    public function test_an_orphaned_custom_role_with_null_tenant_id_is_not_assignable(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        // is_system defaults to false — this shape only exists from the old
        // pre-refactor bug where tenant_id was never resolved on create.
        $orphanedRole = Role::create(['tenant_id' => null, 'name' => 'Orphaned Custom Role']);

        Auth::login($ownerA);
        $service = $this->app->make(StaffService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $service->create([
            'first_name' => 'Should', 'last_name' => 'Fail', 'email' => 'shouldfail@example.test',
            'password' => 'secret123', 'branch_id' => $branch->id, 'role_id' => $orphanedRole->id,
        ], new Request());
    }

    public function test_reset_password_generates_a_new_temporary_password(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $role = $this->makeRole($tenantA, 'Cashier');

        Auth::login($ownerA);
        $service = $this->app->make(StaffService::class);
        $staff = $service->create([
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'reset@example.test',
            'password' => 'Secret123', 'branch_id' => $branch->id, 'role_id' => $role->id,
        ], new Request());

        $originalHash = $staff->user->password_hash;
        $temporaryPassword = $service->resetPassword($staff);

        $this->assertNotEmpty($temporaryPassword);
        $staff->user->refresh();
        $this->assertNotSame($originalHash, $staff->user->password_hash);
        $this->assertTrue($staff->user->must_change_password);
    }

    public function test_duplicate_pin_in_the_same_branch_is_rejected(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $role = $this->makeRole($tenantA, 'Cashier');

        Auth::login($ownerA);
        $service = $this->app->make(StaffService::class);

        $service->create([
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane2@example.test',
            'password' => 'secret123', 'branch_id' => $branch->id, 'role_id' => $role->id,
            'pin_code' => '1234',
        ], new Request());

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $service->create([
            'first_name' => 'John', 'last_name' => 'Roe', 'email' => 'john2@example.test',
            'password' => 'secret123', 'branch_id' => $branch->id, 'role_id' => $role->id,
            'pin_code' => '1234',
        ], new Request());
    }

    public function test_destroy_soft_disables_instead_of_deleting(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $role = $this->makeRole($tenantA, 'Cashier');

        Auth::login($ownerA);
        $service = $this->app->make(StaffService::class);

        $staff = $service->create([
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane3@example.test',
            'password' => 'secret123', 'branch_id' => $branch->id, 'role_id' => $role->id,
        ], new Request());

        $service->deactivate($staff);

        $this->assertNotNull(Staff::find($staff->id));
        $this->assertFalse(Staff::find($staff->id)->is_active);
    }

    public function test_a_tenant_owner_only_sees_their_own_staff_and_by_branch_filters(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');
        $branchA1 = $this->makeBranch($tenantA, 'A-Main');
        $branchA2 = $this->makeBranch($tenantA, 'A-Second');
        $branchB = $this->makeBranch($tenantB, 'B-Main');
        $roleA = $this->makeRole($tenantA, 'Cashier');
        $roleB = $this->makeRole($tenantB, 'Cashier');

        Auth::login($ownerA);
        $service = $this->app->make(StaffService::class);
        $service->create(['first_name' => 'A1', 'last_name' => 'X', 'email' => 'a1@example.test', 'password' => 'secret123', 'branch_id' => $branchA1->id, 'role_id' => $roleA->id], new Request());
        $service->create(['first_name' => 'A2', 'last_name' => 'X', 'email' => 'a2@example.test', 'password' => 'secret123', 'branch_id' => $branchA2->id, 'role_id' => $roleA->id], new Request());

        Auth::login($ownerB);
        $service->create(['first_name' => 'B1', 'last_name' => 'X', 'email' => 'b1@example.test', 'password' => 'secret123', 'branch_id' => $branchB->id, 'role_id' => $roleB->id], new Request());

        Auth::login($ownerA);
        $this->assertSame(2, $service->list([])->total());
        $this->assertSame(1, $service->byBranch($branchA1, [])->total());

        Auth::login($ownerB);
        $this->assertSame(1, $service->list([])->total());
    }
}
