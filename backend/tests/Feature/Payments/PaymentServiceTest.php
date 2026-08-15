<?php

namespace Tests\Feature\Payments;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Covers the Payment resource's Repository/Service migration. Before this,
 * PaymentController::store/show/update/destroy were empty `//` stubs (only
 * index() half-worked, and its search referenced a nonexistent `method`
 * column instead of `payment_method`) — creating/managing a payment via
 * the admin API never worked at all. The "mark order completed once fully
 * paid" side effect (App\Models\Payment::store(), never wired to a route)
 * was also broken even on paper: it read orders.amount_due, a column that
 * doesn't exist, so `null <= 0` was always true in PHP — meaning if it had
 * ever run, it would have marked every order completed regardless of
 * whether it was actually paid. Rebuilt correctly here against orders.total
 * _amount and a real sum of completed payments.
 */
class PaymentServiceTest extends TestCase
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

    private function makeOrder(Branch $branch, string $orderNumber, float $totalAmount): Order
    {
        return Order::create([
            'branch_id' => $branch->id,
            'order_number' => $orderNumber,
            'total_amount' => $totalAmount,
        ]);
    }

    public function test_a_tenant_cannot_see_another_tenants_payment_via_direct_lookup(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerB);
        $branchB = $this->makeBranch($tenantB, 'Branch B');
        $orderB = $this->makeOrder($branchB, 'ORD-B-1', 20);
        $paymentB = Payment::create([
            'order_id' => $orderB->id,
            'branch_id' => $branchB->id,
            'payment_method' => 'cash',
            'amount' => 20,
            'status' => 'completed',
        ]);

        Auth::login($ownerA);
        $this->assertNull(Payment::find($paymentB->id));
        $this->assertNotNull(Payment::withoutGlobalScopes()->find($paymentB->id));
    }

    public function test_create_derives_branch_id_from_the_order_not_the_client(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);
        $branch = $this->makeBranch($tenantA, 'Branch A');
        $order = $this->makeOrder($branch, 'ORD-A-1', 30);

        $service = $this->app->make(PaymentService::class);
        $payment = $service->create([
            'order_id' => $order->id,
            'branch_id' => 'not-the-real-branch-id',
            'payment_method' => 'cash',
            'amount' => 30,
        ]);

        $this->assertSame($branch->id, $payment->branch_id);
    }

    public function test_a_cross_tenant_order_id_is_rejected(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, ] = $this->makeTenantWithOwner('TenantB');
        $branchB = $this->makeBranch($tenantB, 'Branch B');
        $orderB = $this->makeOrder($branchB, 'ORD-B-2', 10);

        Auth::login($ownerA);
        $service = $this->app->make(PaymentService::class);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $service->create([
            'order_id' => $orderB->id,
            'payment_method' => 'cash',
            'amount' => 10,
        ]);
    }

    public function test_a_fully_paid_order_is_marked_completed(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);
        $branch = $this->makeBranch($tenantA, 'Branch A');
        $order = $this->makeOrder($branch, 'ORD-A-3', 50);

        $service = $this->app->make(PaymentService::class);
        $service->create([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'amount' => 50,
        ]);

        $this->assertSame('completed', $order->refresh()->status);
    }

    public function test_a_partially_paid_order_is_not_marked_completed(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);
        $branch = $this->makeBranch($tenantA, 'Branch A');
        $order = $this->makeOrder($branch, 'ORD-A-4', 50);

        $service = $this->app->make(PaymentService::class);
        $service->create([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'amount' => 20,
        ]);

        $this->assertNotSame('completed', $order->refresh()->status);
    }

    public function test_a_pending_payment_does_not_complete_the_order(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);
        $branch = $this->makeBranch($tenantA, 'Branch A');
        $order = $this->makeOrder($branch, 'ORD-A-5', 50);

        $service = $this->app->make(PaymentService::class);
        $service->create([
            'order_id' => $order->id,
            'payment_method' => 'card',
            'amount' => 50,
            'status' => 'pending',
        ]);

        $this->assertNotSame('completed', $order->refresh()->status);
    }

    public function test_update_and_delete_go_through_the_repository(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);
        $branch = $this->makeBranch($tenantA, 'Branch A');
        $order = $this->makeOrder($branch, 'ORD-A-6', 15);

        $service = $this->app->make(PaymentService::class);
        $payment = $service->create(['order_id' => $order->id, 'payment_method' => 'cash', 'amount' => 15]);

        $updated = $service->update($payment, ['receipt_number' => 'RCPT-001']);
        $this->assertSame('RCPT-001', $updated->receipt_number);

        $service->delete($payment);
        $this->assertNull(Payment::find($payment->id));
    }
}
