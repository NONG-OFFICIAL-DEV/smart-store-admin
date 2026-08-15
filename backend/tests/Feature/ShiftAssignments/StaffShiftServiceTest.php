<?php

namespace Tests\Feature\ShiftAssignments;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\StaffShift;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StaffShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Covers the StaffShift (shift-assignment) resource's Repository/Service
 * migration (see .claude/skills/migrate-resource-to-repository). Two real
 * bugs found while migrating: (1) the old controller manually re-filtered
 * by tenant on top of the already-active TenantScope (double-filtering,
 * removed here); (2) the duplicate-assignment check didn't match the real
 * DB unique constraint (missing branch_id), so a same-shift/staff/date
 * assignment at a different branch passed the app check and then 500'd on
 * the DB constraint instead.
 */
class StaffShiftServiceTest extends TestCase
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

    public function test_a_tenant_owner_only_sees_assignments_at_their_own_branches(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');
        $branchA = $this->makeBranch($tenantA, 'A-Main');
        $branchB = $this->makeBranch($tenantB, 'B-Main');
        $staffA = $this->makeStaff($tenantA, $branchA, 'a@example.test');
        $staffB = $this->makeStaff($tenantB, $branchB, 'b@example.test');
        $shiftA = Shift::create(['tenant_id' => $tenantA->id, 'name' => 'Morning', 'start_time' => '08:00', 'end_time' => '16:00']);
        $shiftB = Shift::create(['tenant_id' => $tenantB->id, 'name' => 'Morning', 'start_time' => '08:00', 'end_time' => '16:00']);

        StaffShift::create(['shift_id' => $shiftA->id, 'staff_id' => $staffA->id, 'branch_id' => $branchA->id, 'shift_date' => today()]);
        StaffShift::create(['shift_id' => $shiftB->id, 'staff_id' => $staffB->id, 'branch_id' => $branchB->id, 'shift_date' => today()]);

        Auth::login($ownerA);
        $service = $this->app->make(StaffShiftService::class);
        $this->assertSame(1, $service->list([])->total());

        Auth::login($ownerB);
        $this->assertSame(1, $service->list([])->total());
    }

    public function test_duplicate_assignment_on_same_shift_staff_date_is_rejected_even_across_branches(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch1 = $this->makeBranch($tenantA, 'Branch1');
        $branch2 = $this->makeBranch($tenantA, 'Branch2');
        $staff = $this->makeStaff($tenantA, $branch1, 'dup@example.test');
        $shift = Shift::create(['tenant_id' => $tenantA->id, 'name' => 'Morning', 'start_time' => '08:00', 'end_time' => '16:00']);

        Auth::login($ownerA);
        $service = $this->app->make(StaffShiftService::class);

        $service->create([
            'shift_id' => $shift->id, 'staff_id' => $staff->id,
            'branch_id' => $branch1->id, 'shift_date' => today()->toDateString(),
        ]);

        $this->expectException(ValidationException::class);

        // Same shift+staff+date, DIFFERENT branch — the old app-level check
        // (which included branch_id) would have let this through only to
        // crash on the DB's actual unique constraint.
        $service->create([
            'shift_id' => $shift->id, 'staff_id' => $staff->id,
            'branch_id' => $branch2->id, 'shift_date' => today()->toDateString(),
        ]);
    }

    public function test_clock_in_then_out_guard_clauses(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $staff = $this->makeStaff($tenantA, $branch, 'clock@example.test');
        $shift = Shift::create(['tenant_id' => $tenantA->id, 'name' => 'Morning', 'start_time' => '08:00', 'end_time' => '16:00']);

        Auth::login($ownerA);
        $service = $this->app->make(StaffShiftService::class);
        $assignment = $service->create([
            'shift_id' => $shift->id, 'staff_id' => $staff->id,
            'branch_id' => $branch->id, 'shift_date' => today()->toDateString(),
        ]);

        $assignment = $service->clockIn($assignment);
        $this->assertNotNull($assignment->actual_start);

        $this->expectException(ValidationException::class);
        $service->clockIn($assignment);
    }

    public function test_cannot_clock_out_before_clocking_in(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $staff = $this->makeStaff($tenantA, $branch, 'clockout@example.test');
        $shift = Shift::create(['tenant_id' => $tenantA->id, 'name' => 'Morning', 'start_time' => '08:00', 'end_time' => '16:00']);

        Auth::login($ownerA);
        $service = $this->app->make(StaffShiftService::class);
        $assignment = $service->create([
            'shift_id' => $shift->id, 'staff_id' => $staff->id,
            'branch_id' => $branch->id, 'shift_date' => today()->toDateString(),
        ]);

        $this->expectException(ValidationException::class);
        $service->clockOut($assignment);
    }

    public function test_actual_minutes_and_status_accessors_work_after_the_shift_template_typo_fix(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $staff = $this->makeStaff($tenantA, $branch, 'accessor@example.test');
        $shift = Shift::create(['tenant_id' => $tenantA->id, 'name' => 'Morning', 'start_time' => '08:00', 'end_time' => '16:00', 'break_minutes' => 30]);

        $assignment = StaffShift::create([
            'shift_id' => $shift->id, 'staff_id' => $staff->id, 'branch_id' => $branch->id,
            'shift_date' => today(),
            'actual_start' => today()->setTime(8, 0),
            'actual_end' => today()->setTime(16, 0),
        ]);

        $this->assertSame('completed', $assignment->status);
        $this->assertSame(450, $assignment->actual_minutes); // 8h - 30min break

        // "scheduled"/"absent" branches are the ones that actually hit the
        // buggy line (they read $this->shift->start_time, previously
        // $this->shiftTemplate->start_time — null->start_time would throw).
        $future = StaffShift::create([
            'shift_id' => $shift->id, 'staff_id' => $staff->id, 'branch_id' => $branch->id,
            'shift_date' => today()->addDay(),
        ]);
        $this->assertSame('scheduled', $future->status);
    }
}
