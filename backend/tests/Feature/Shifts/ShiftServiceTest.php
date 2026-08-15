<?php

namespace Tests\Feature\Shifts;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\StaffShift;
use App\Models\Tenant;
use App\Models\User;
use App\Http\Controllers\Api\ShiftController;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Covers the Shift (shift-template) resource's Repository/Service
 * migration (see .claude/skills/migrate-resource-to-repository), plus the
 * three routes on this exact controller that pointed at controller methods
 * which didn't exist at all (byStaff/clockIn/clockOut) — a guaranteed 500
 * if ever called, fixed as part of thinning this controller.
 */
class ShiftServiceTest extends TestCase
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

    private function makeStaff(Tenant $tenant, Branch $branch, string $email): Staff
    {
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Cashier']);
        $user = User::create(['email' => $email, 'first_name' => 'S', 'last_name' => 'Taff', 'is_super_admin' => false]);

        return Staff::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_a_tenant_owner_only_sees_their_own_shifts(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        Shift::create(['tenant_id' => $tenantA->id, 'name' => 'Morning', 'start_time' => '08:00', 'end_time' => '16:00']);
        Shift::create(['tenant_id' => $tenantB->id, 'name' => 'Night', 'start_time' => '22:00', 'end_time' => '06:00']);

        Auth::login($ownerA);
        $service = $this->app->make(ShiftService::class);
        $this->assertSame(1, $service->list([])->total());

        Auth::login($ownerB);
        $this->assertSame(1, $service->list([])->total());
    }

    public function test_create_assigns_the_resolved_tenant_id_not_a_spoofed_one(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerA);
        $service = $this->app->make(ShiftService::class);

        $shift = $service->create([
            'name' => 'Morning',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'tenant_id' => $tenantB->id,
        ], new Request());

        $this->assertSame($tenantA->id, $shift->tenant_id);
    }

    public function test_overnight_shift_duration_accessor_still_works_through_the_resource(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $shift = Shift::create([
            'tenant_id' => $tenantA->id, 'name' => 'Night',
            'start_time' => '22:00', 'end_time' => '06:00', 'break_minutes' => 30,
        ]);

        $this->assertTrue($shift->is_overnight);
        $this->assertSame('7h 30m', $shift->duration);
    }

    public function test_clock_in_and_out_by_staff_for_todays_assignment(): void
    {
        // Exercises the actual controller methods directly (bypassing
        // jwt.auth middleware, which Auth::login() doesn't satisfy in an
        // HTTP test) — these three routes previously pointed at controller
        // methods that didn't exist at all, so this is what confirms the fix.
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $staff = $this->makeStaff($tenantA, $branch, 'clockin@example.test');
        $shift = Shift::create(['tenant_id' => $tenantA->id, 'name' => 'Morning', 'start_time' => '08:00', 'end_time' => '16:00']);

        Auth::login($ownerA);
        StaffShift::create([
            'shift_id' => $shift->id,
            'staff_id' => $staff->id,
            'branch_id' => $branch->id,
            'shift_date' => today(),
        ]);

        $controller = $this->app->make(ShiftController::class);

        $inResponse = $controller->clockIn($staff);
        $this->assertSame(200, $inResponse->getStatusCode());

        $again = $controller->clockIn($staff);
        $this->assertSame(422, $again->getStatusCode());

        $outResponse = $controller->clockOut($staff);
        $this->assertSame(200, $outResponse->getStatusCode());

        $assignment = StaffShift::where('staff_id', $staff->id)->first();
        $this->assertNotNull($assignment->actual_start);
        $this->assertNotNull($assignment->actual_end);
    }
}
