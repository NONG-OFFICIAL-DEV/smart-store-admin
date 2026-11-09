<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\CheckPermission;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Covers the comma-separated "any one of these permissions" support added to
 * CheckPermission — needed because PATCH orders/{order}/serve is reachable
 * from both the Mobile Orders Carousel (counter staff, orders.manage) and
 * Kitchen Display (kitchen staff, kitchen.manage), and those are two
 * genuinely separate permission groups (see PermissionSeeder) — a
 * kitchen-only role previously got a 403 clicking "Serve" since the route
 * only checked orders.manage.
 */
class CheckPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithOwner(string $name): array
    {
        $owner = User::create([
            'email' => strtolower($name).'@example.test',
            'first_name' => $name,
            'last_name' => 'Owner',
        ]);

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => strtolower($name).'-'.substr((string) $owner->id, 0, 8),
            'owner_user_id' => $owner->id,
        ]);

        return [$tenant, $owner];
    }

    private function makeStaffWithPermissions(Tenant $tenant, array $permissionCodes): User
    {
        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
            'address_line1' => '123 Main St',
            'city' => 'Phnom Penh',
        ]);

        $user = User::create([
            'email' => strtolower(implode('-', $permissionCodes) ?: 'none').'@example.test',
            'first_name' => 'Staff',
            'last_name' => 'Person',
        ]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Custom Role']);
        $permissionIds = collect($permissionCodes)->map(
            fn ($code) => Permission::firstOrCreate(['code' => $code], ['group' => explode('.', $code)[0]])->id
        );
        $role->permissions()->sync($permissionIds);

        Staff::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'employee_code' => 'EMP-'.substr((string) $user->id, 0, 8),
        ]);

        return $user;
    }

    private function callMiddleware(User $user, string $permission): \Symfony\Component\HttpFoundation\Response
    {
        Auth::login($user);

        return (new CheckPermission())->handle(
            Request::create('/test'),
            fn () => response('ok'),
            $permission
        );
    }

    public function test_a_staff_user_with_only_the_second_listed_permission_passes(): void
    {
        [$tenant] = $this->makeTenantWithOwner('TenantA');
        $kitchenStaff = $this->makeStaffWithPermissions($tenant, ['kitchen.manage']);

        $response = $this->callMiddleware($kitchenStaff, 'orders.manage,kitchen.manage');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_a_staff_user_with_only_the_first_listed_permission_also_passes(): void
    {
        [$tenant] = $this->makeTenantWithOwner('TenantA');
        $counterStaff = $this->makeStaffWithPermissions($tenant, ['orders.manage']);

        $response = $this->callMiddleware($counterStaff, 'orders.manage,kitchen.manage');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_a_staff_user_with_neither_listed_permission_is_rejected(): void
    {
        [$tenant] = $this->makeTenantWithOwner('TenantA');
        $unrelatedStaff = $this->makeStaffWithPermissions($tenant, ['products.manage']);

        $response = $this->callMiddleware($unrelatedStaff, 'orders.manage,kitchen.manage');

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_a_plain_single_permission_string_keeps_working_as_before(): void
    {
        [$tenant] = $this->makeTenantWithOwner('TenantA');
        $staff = $this->makeStaffWithPermissions($tenant, ['orders.manage']);

        $this->assertSame(200, $this->callMiddleware($staff, 'orders.manage')->getStatusCode());
        $this->assertSame(403, $this->callMiddleware($staff, 'kitchen.manage')->getStatusCode());
    }
}
