<?php

namespace Tests\Feature\Loyalty;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Covers LoyaltyObserver's reversal side — see RefundService::create(),
 * which is the only call site that ever transitions an order to
 * 'refunded' (via Order::updateStatus()).
 */
class LoyaltyRefundReversalTest extends TestCase
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

    private function makeBranch(Tenant $tenant): Branch
    {
        return Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
            'address_line1' => '123 Main St',
            'city' => 'Phnom Penh',
        ]);
    }

    private function makeStaff(Tenant $tenant, Branch $branch): Staff
    {
        $user = User::create([
            'email' => 'cashier-'.strtolower($tenant->slug).'@example.test',
            'first_name' => 'Cashier',
            'last_name' => 'User',
        ]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Cashier']);

        return Staff::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_a_full_refund_reverses_the_earned_points(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $staff = $this->makeStaff($tenant, $branch);
        $customer = Customer::create(['tenant_id' => $tenant->id, 'first_name' => 'Sok']);

        $order = Order::create([
            'branch_id' => $branch->id,
            'order_number' => 'ORD-REF-1',
            'customer_id' => $customer->id,
            'status' => 'completed',
            'total_amount' => 50,
        ]);

        $this->assertSame(1, $customer->refresh()->loyalty_points);

        $payment = Payment::create([
            'order_id' => $order->id,
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'amount' => 50,
            'status' => 'completed',
        ]);

        $service = $this->app->make(RefundService::class);
        $service->create($payment, ['amount' => 50, 'reason' => 'Customer request', 'staff_id' => $staff->id]);

        $this->assertSame('refunded', $order->refresh()->status);
        $this->assertSame(0, $customer->refresh()->loyalty_points);

        $this->assertDatabaseHas('loyalty_transactions', [
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'type' => 'adjust',
            'points' => -1,
        ]);
    }

    public function test_refunding_an_order_with_no_earn_transaction_does_not_touch_the_balance(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantB');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $staff = $this->makeStaff($tenant, $branch);
        $customer = Customer::create(['tenant_id' => $tenant->id, 'first_name' => 'Dara']);
        $customer->addPoints(20, null, 'adjust');

        // Created pending, never completed — the observer never ran
        // awardPoints() for it, so there's nothing to reverse.
        $order = Order::create([
            'branch_id' => $branch->id,
            'order_number' => 'ORD-REF-2',
            'customer_id' => $customer->id,
            'status' => 'pending',
            'total_amount' => 50,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'amount' => 50,
            'status' => 'completed',
        ]);

        $service = $this->app->make(RefundService::class);
        $service->create($payment, ['amount' => 50, 'reason' => 'Customer request', 'staff_id' => $staff->id]);

        $this->assertSame('refunded', $order->refresh()->status);
        $this->assertSame(20, $customer->refresh()->loyalty_points);
        $this->assertSame(0, LoyaltyTransaction::where('order_id', $order->id)->count());
    }
}
