<?php

namespace Tests\Feature\Roles;

use App\Exceptions\SystemRoleLockedException;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Covers the Role resource's Repository/Service migration (see
 * .claude/skills/migrate-resource-to-repository). Focused on the two real
 * bugs found while migrating: (1) roles created via the API never had
 * tenant_id resolved, so they got tenant_id = NULL, which TenantScope
 * treats as "shared across every tenant" — the exact class of leak
 * tests/Feature/Security/TenantScopeTest.php exists to guard against.
 * (2) destroy() never checked is_system, unlike update().
 */
class RoleServiceTest extends TestCase
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

    public function test_create_assigns_the_resolved_tenant_id_never_null(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');

        Auth::login($ownerA);
        $service = $this->app->make(RoleService::class);

        $role = $service->create(['name' => 'Shift Lead'], new Request());

        $this->assertSame($tenantA->id, $role->tenant_id);
        $this->assertFalse($role->is_system);
    }

    public function test_a_role_created_by_one_tenant_is_not_visible_to_another(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerA);
        $service = $this->app->make(RoleService::class);
        $service->create(['name' => 'Custom A Role'], new Request());

        Auth::login($ownerB);
        $names = $service->list([])->pluck('name')->all();
        $this->assertNotContains('Custom A Role', $names);
    }

    public function test_is_system_cannot_be_set_via_create_even_if_sent(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');

        Auth::login($ownerA);
        $service = $this->app->make(RoleService::class);

        $role = $service->create(['name' => 'Sneaky', 'is_system' => true], new Request());

        $this->assertFalse($role->is_system);
    }

    public function test_system_roles_cannot_be_updated_or_deleted(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $systemRole = Role::create(['tenant_id' => null, 'name' => 'Owner', 'is_system' => true]);

        Auth::login($ownerA);
        $service = $this->app->make(RoleService::class);

        $this->expectException(SystemRoleLockedException::class);
        $service->update($systemRole, ['name' => 'Hacked']);
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        $systemRole = Role::create(['tenant_id' => null, 'name' => 'Manager', 'is_system' => true]);

        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);
        $service = $this->app->make(RoleService::class);

        $this->expectException(SystemRoleLockedException::class);
        $service->delete($systemRole);
    }

    public function test_deleting_a_role_still_assigned_to_staff_is_rejected_with_a_clear_message(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = Branch::create([
            'tenant_id' => $tenantA->id, 'name' => 'Main', 'address_line1' => '123 Main St', 'city' => 'Phnom Penh',
        ]);
        $staffUser = User::create(['first_name' => 'Staff', 'last_name' => 'Person', 'email' => 'staffperson2@example.test']);

        Auth::login($ownerA);
        $service = $this->app->make(RoleService::class);
        $role = $service->create(['name' => 'Cashier'], new Request());

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $tenantA->id, 'branch_id' => $branch->id, 'user_id' => $staffUser->id,
            'role_id' => $role->id, 'employee_code' => 'EMP-1',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->delete($role);
    }

    public function test_list_can_filter_by_is_system(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Role::create(['tenant_id' => null, 'name' => 'Owner', 'is_system' => true]);

        Auth::login($ownerA);
        $service = $this->app->make(RoleService::class);
        $service->create(['name' => 'Custom Cashier'], new Request());

        $systemOnly = $service->list(['is_system' => true])->pluck('name')->all();
        $this->assertSame(['Owner'], $systemOnly);

        $customOnly = $service->list(['is_system' => false])->pluck('name')->all();
        $this->assertSame(['Custom Cashier'], $customOnly);
    }

    public function test_create_rejects_a_case_insensitive_duplicate_name_within_the_same_tenant(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');

        Auth::login($ownerA);
        $service = $this->app->make(RoleService::class);
        $service->create(['name' => 'Shift Lead'], new Request());

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->create(['name' => 'shift lead'], new Request());
    }

    public function test_two_different_tenants_may_each_use_the_same_role_name(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerA);
        $service = $this->app->make(RoleService::class);
        $service->create(['name' => 'Shift Lead'], new Request());

        Auth::login($ownerB);
        $role = $service->create(['name' => 'Shift Lead'], new Request());

        $this->assertSame($tenantB->id, $role->tenant_id);
    }

    public function test_update_rejects_renaming_to_a_duplicate_but_allows_keeping_the_same_name(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');

        Auth::login($ownerA);
        $service = $this->app->make(RoleService::class);
        $service->create(['name' => 'Cashier'], new Request());
        $shiftLead = $service->create(['name' => 'Shift Lead'], new Request());

        // No-op rename (same name) must not trip the uniqueness check against itself.
        $unchanged = $service->update($shiftLead, ['name' => 'Shift Lead']);
        $this->assertSame('Shift Lead', $unchanged->name);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->update($shiftLead, ['name' => 'Cashier']);
    }

    public function test_sync_permissions_updates_role_permissions_and_is_reusable_from_update(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $perm = Permission::create(['code' => 'customers.manage', 'group' => 'customers']);

        Auth::login($ownerA);
        $service = $this->app->make(RoleService::class);
        $role = $service->create(['name' => 'Cashier'], new Request());

        $updated = $service->update($role, ['permission_ids' => [$perm->id]]);

        $this->assertTrue($updated->permissions->contains('id', $perm->id));
    }
}
