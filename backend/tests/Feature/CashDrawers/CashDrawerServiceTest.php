<?php

namespace Tests\Feature\CashDrawers;

use App\Models\Branch;
use App\Models\CashDrawer;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CashDrawerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * CashDrawerController::store/show/update/destroy were empty `//` stubs,
 * and the routes referencing `open`/`close` pointed at controller methods
 * that didn't exist at all (guaranteed 500) — the real business logic
 * lived only in App\Models\CashDrawer::open()/close(), never wired to a
 * route. Moved into CashDrawerService, now actually reachable. Also fixed:
 * index()'s search filtered a nonexistent `status` column.
 */
class CashDrawerServiceTest extends TestCase
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

    private function makeStaff(Tenant $tenant, Branch $branch, string $name): Staff
    {
        $user = User::create([
            'email' => strtolower($name).'@example.test',
            'first_name' => $name,
            'last_name' => 'Cashier',
        ]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => $name.' Role']);

        return Staff::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_open_creates_a_session_with_the_given_opening_float(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant, 'Branch A');
        $staff = $this->makeStaff($tenant, $branch, 'Cashier');

        $service = $this->app->make(CashDrawerService::class);
        $drawer = $service->open(['branch_id' => $branch->id, 'staff_id' => $staff->id, 'opening_float' => 100]);

        $this->assertSame('100.00', $drawer->opening_float);
        $this->assertNull($drawer->closed_at);
    }

    public function test_close_computes_expected_cash_from_completed_cash_payments_since_opening(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantB');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant, 'Branch B');
        $staff = $this->makeStaff($tenant, $branch, 'Cashier');

        $service = $this->app->make(CashDrawerService::class);
        $drawer = $service->open(['branch_id' => $branch->id, 'staff_id' => $staff->id, 'opening_float' => 100]);

        $order = \App\Models\Order::create(['branch_id' => $branch->id, 'order_number' => 'ORD-1', 'total_amount' => 50]);
        // cash_drawer_id is the real attribution now (not branch+paid_at
        // inference) — every cash payment created through checkout sets it,
        // so the fixture here must too.
        Payment::create([
            'order_id' => $order->id, 'branch_id' => $branch->id, 'payment_method' => 'cash',
            'cash_drawer_id' => $drawer->id,
            'amount' => 50, 'status' => 'completed', 'paid_at' => now(),
        ]);
        // A card payment should NOT count toward cash expected total.
        Payment::create([
            'order_id' => $order->id, 'branch_id' => $branch->id, 'payment_method' => 'card',
            'amount' => 999, 'status' => 'completed', 'paid_at' => now(),
        ]);

        $closed = $service->close($drawer, 150, 'End of shift');

        $this->assertSame('150.00', $closed->expected_cash);
        $this->assertSame('150.00', $closed->actual_cash);
        $this->assertSame('0.00', $closed->variance);
        $this->assertNotNull($closed->closed_at);
    }

    public function test_a_tenant_only_sees_their_own_branchs_drawers(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantC');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantD');

        Auth::login($ownerB);
        $branchB = $this->makeBranch($tenantB, 'Branch D');
        $staffB = $this->makeStaff($tenantB, $branchB, 'CashierB');
        $service = $this->app->make(CashDrawerService::class);
        $service->open(['branch_id' => $branchB->id, 'staff_id' => $staffB->id, 'opening_float' => 50]);

        Auth::login($ownerA);
        $results = $service->list([]);

        $this->assertSame(0, $results->total());
    }

    public function test_open_rejects_a_second_open_shift_on_the_same_branch(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantE');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant, 'Branch E');
        $staff = $this->makeStaff($tenant, $branch, 'Cashier');

        $service = $this->app->make(CashDrawerService::class);
        $service->open(['branch_id' => $branch->id, 'staff_id' => $staff->id, 'opening_float' => 100]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->open(['branch_id' => $branch->id, 'staff_id' => $staff->id, 'opening_float' => 50]);
    }

    public function test_cash_in_and_cash_out_are_reflected_in_status(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantF');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant, 'Branch F');
        $staff = $this->makeStaff($tenant, $branch, 'Cashier');

        $service = $this->app->make(CashDrawerService::class);
        $drawer = $service->open(['branch_id' => $branch->id, 'staff_id' => $staff->id, 'opening_float' => 100]);

        $service->cashIn($drawer, 20, 'Change float top-up', $staff);
        $service->cashOut($drawer, 30, 'Petty cash for supplies', $staff);

        $status = $service->status($drawer);

        $this->assertSame(100.0, $status['opening_float']);
        $this->assertSame(20.0, $status['cash_in']);
        $this->assertSame(30.0, $status['cash_out']);
        // 100 + 0 (no sales) + 20 - 30 - 0 = 90
        $this->assertSame(90.0, $status['expected_cash']);
    }

    public function test_cash_out_rejects_insufficient_balance(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantG');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant, 'Branch G');
        $staff = $this->makeStaff($tenant, $branch, 'Cashier');

        $service = $this->app->make(CashDrawerService::class);
        $drawer = $service->open(['branch_id' => $branch->id, 'staff_id' => $staff->id, 'opening_float' => 50]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->cashOut($drawer, 100, 'Too much', $staff);
    }

    public function test_cash_in_and_cash_out_are_rejected_on_a_closed_drawer(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantH');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant, 'Branch H');
        $staff = $this->makeStaff($tenant, $branch, 'Cashier');

        $service = $this->app->make(CashDrawerService::class);
        $drawer = $service->open(['branch_id' => $branch->id, 'staff_id' => $staff->id, 'opening_float' => 100]);
        $service->close($drawer, 100, null);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->cashIn($drawer, 10, 'Too late', $staff);
    }

    public function test_expected_cash_formula_combines_sales_cash_in_out_and_cash_refunds(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantI');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant, 'Branch I');
        $staff = $this->makeStaff($tenant, $branch, 'Cashier');

        $service = $this->app->make(CashDrawerService::class);
        $drawer = $service->open(['branch_id' => $branch->id, 'staff_id' => $staff->id, 'opening_float' => 100]);

        $order = \App\Models\Order::create(['branch_id' => $branch->id, 'order_number' => 'ORD-2', 'total_amount' => 250]);

        $cashPayment = Payment::create([
            'order_id' => $order->id, 'branch_id' => $branch->id, 'payment_method' => 'cash',
            'cash_drawer_id' => $drawer->id,
            'amount' => 250, 'status' => 'completed', 'paid_at' => now(),
        ]);
        // Reported alongside the drawer but NOT part of expected_cash.
        Payment::create([
            'order_id' => $order->id, 'branch_id' => $branch->id, 'payment_method' => 'card',
            'amount' => 80, 'status' => 'completed', 'paid_at' => now(),
        ]);

        $service->cashIn($drawer, 20, 'Top-up', $staff);
        $service->cashOut($drawer, 30, 'Supplies', $staff);

        \App\Models\Refund::create([
            'payment_id' => $cashPayment->id, 'order_id' => $order->id, 'staff_id' => $staff->id,
            'cash_drawer_id' => $drawer->id,
            'amount' => 10, 'reason' => 'Customer complaint', 'method' => 'cash', 'status' => 'completed',
        ]);

        $status = $service->status($drawer);

        $this->assertSame(250.0, $status['cash_sales']);
        $this->assertSame(80.0, $status['non_cash_sales']);
        $this->assertSame(20.0, $status['cash_in']);
        $this->assertSame(30.0, $status['cash_out']);
        $this->assertSame(10.0, $status['cash_refunds']);
        // 100 (opening) + 250 (cash sales) + 20 (cash in) - 30 (cash out) - 10 (cash refund) = 330
        $this->assertSame(330.0, $status['expected_cash']);

        $closed = $service->close($drawer, 330, null);
        $this->assertSame('330.00', $closed->expected_cash);
        $this->assertSame('0.00', $closed->variance);
    }
}
