<?php

namespace Tests\Feature\Roles;

use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Covers app/Console/Commands/FixOrphanedRoles.php — the repair for roles
 * left with tenant_id = NULL by the historical create() bug (see
 * RoleService::create()'s own comment, and RoleServiceTest/StaffServiceTest
 * for the assertRoleAssignable side of this). Three shapes get fixed:
 * single-tenant backfill and multi-tenant clone are covered elsewhere by
 * inspection; this focuses on the newer duplicate-merge + template-
 * promotion pass for orphaned roles nobody's staff reference at all.
 */
class FixOrphanedRolesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithOwner(string $name): Tenant
    {
        $owner = User::create([
            'email' => strtolower($name).'@example.test',
            'first_name' => $name,
            'last_name' => 'Owner',
        ]);

        return Tenant::create([
            'name' => $name,
            'slug' => strtolower($name).'-'.substr((string) $owner->id, 0, 8),
            'owner_user_id' => $owner->id,
        ]);
    }

    public function test_unused_orphaned_role_is_promoted_to_a_shared_template(): void
    {
        $role = Role::create(['tenant_id' => null, 'name' => 'Cashier', 'is_system' => false]);

        Artisan::call('roles:fix-orphaned');

        $role->refresh();
        $this->assertTrue($role->is_system);
        $this->assertNull($role->tenant_id);
    }

    public function test_duplicate_named_orphans_are_merged_into_one_promoted_template(): void
    {
        $permA = Permission::create(['code' => 'orders.manage', 'group' => 'orders']);
        $permB = Permission::create(['code' => 'payments.manage', 'group' => 'payments']);

        $first = Role::create(['tenant_id' => null, 'name' => 'Cashier', 'is_system' => false]);
        $first->permissions()->sync([$permA->id]);

        $second = Role::create(['tenant_id' => null, 'name' => '  cashier  ', 'is_system' => false]);
        $second->permissions()->sync([$permB->id]);

        Artisan::call('roles:fix-orphaned');

        $this->assertNull(Role::withoutGlobalScopes()->find($second->id));

        $survivor = Role::withoutGlobalScopes()->find($first->id);
        $this->assertNotNull($survivor);
        $this->assertTrue($survivor->is_system);
        $this->assertEqualsCanonicalizing(
            [$permA->id, $permB->id],
            $survivor->permissions->pluck('id')->all()
        );
    }

    public function test_orphan_merges_into_an_existing_real_template_instead_of_creating_a_second_one(): void
    {
        $template = Role::create(['tenant_id' => null, 'name' => 'Owner', 'is_system' => true]);
        $orphan = Role::create(['tenant_id' => null, 'name' => 'owner', 'is_system' => false]);

        Artisan::call('roles:fix-orphaned');

        $this->assertNull(Role::withoutGlobalScopes()->find($orphan->id));
        $this->assertNotNull(Role::withoutGlobalScopes()->find($template->id));
        $this->assertSame(1, Role::withoutGlobalScopes()->whereNull('tenant_id')->count());
    }

    public function test_orphan_with_staff_from_one_tenant_is_backfilled_not_promoted(): void
    {
        $tenant = $this->makeTenantWithOwner('TenantA');
        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main',
            'address_line1' => '123 Main St',
            'city' => 'Phnom Penh',
        ]);
        $staffUser = User::create(['first_name' => 'Staff', 'last_name' => 'Person', 'email' => 'staffperson@example.test']);
        $role = Role::create(['tenant_id' => null, 'name' => 'Manager', 'is_system' => false]);
        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'user_id' => $staffUser->id,
            'role_id' => $role->id,
            'employee_code' => 'EMP-1',
        ]);

        Artisan::call('roles:fix-orphaned');

        $role->refresh();
        $this->assertSame($tenant->id, $role->tenant_id);
        $this->assertFalse($role->is_system);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $role = Role::create(['tenant_id' => null, 'name' => 'Cashier', 'is_system' => false]);

        Artisan::call('roles:fix-orphaned', ['--dry-run' => true]);

        $role->refresh();
        $this->assertFalse($role->is_system);
        $this->assertNull($role->tenant_id);
    }
}
