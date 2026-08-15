<?php

namespace Tests\Feature\Refunds;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * refunds had no #[ScopedBy] at all — payment_id/order_id are its only
 * links back to a tenant, and it's reachable via a flat
 * Route::apiResource('refunds', ...)->only(['index','show']), so any user
 * with payments.manage in ANY tenant could list/view every other tenant's
 * refunds. Also: the original (dead) Refund::store() always fully
 * "refunded" the payment/order regardless of the refund amount, with no
 * check against amounts already refunded — fixed to reject over-refunds
 * and only fully mark refunded once the cumulative amount covers it.
 */
class RefundServiceTest extends TestCase
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

    private function makePaidOrder(Branch $branch, string $orderNumber, float $amount): array
    {
        $order = Order::create([
            'branch_id' => $branch->id,
            'order_number' => $orderNumber,
            'total_amount' => $amount,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'amount' => $amount,
            'status' => 'completed',
        ]);

        return [$order, $payment];
    }

    public function test_a_tenant_cannot_see_another_tenants_refund_via_direct_lookup(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerB);
        $branchB = $this->makeBranch($tenantB, 'Branch B');
        $staffB = $this->makeStaff($tenantB, $branchB, 'StaffB');
        [, $paymentB] = $this->makePaidOrder($branchB, 'ORD-B-1', 40);
        $service = $this->app->make(RefundService::class);
        $refundB = $service->create($paymentB, ['amount' => 40, 'reason' => 'Customer request', 'staff_id' => $staffB->id]);

        Auth::login($ownerA);
        $this->assertNull(Refund::find($refundB->id));
        $this->assertNotNull(Refund::withoutGlobalScopes()->find($refundB->id));
    }

    public function test_a_full_refund_marks_the_payment_and_order_refunded(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);
        $branch = $this->makeBranch($tenantA, 'Branch A');
        $staff = $this->makeStaff($tenantA, $branch, 'StaffA1');
        [$order, $payment] = $this->makePaidOrder($branch, 'ORD-A-1', 50);

        $service = $this->app->make(RefundService::class);
        $service->create($payment, ['amount' => 50, 'reason' => 'Customer request', 'staff_id' => $staff->id]);

        $this->assertSame('refunded', $payment->refresh()->status);
        $this->assertSame('refunded', $order->refresh()->status);
    }

    public function test_a_partial_refund_marks_the_payment_partially_refunded_and_leaves_the_order_alone(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);
        $branch = $this->makeBranch($tenantA, 'Branch A');
        $staff = $this->makeStaff($tenantA, $branch, 'StaffA2');
        [$order, $payment] = $this->makePaidOrder($branch, 'ORD-A-2', 50);

        $service = $this->app->make(RefundService::class);
        $service->create($payment, ['amount' => 20, 'reason' => 'Partial return', 'staff_id' => $staff->id]);

        $this->assertSame('partially_refunded', $payment->refresh()->status);
        $this->assertNotSame('refunded', $order->refresh()->status);
    }

    public function test_refunding_more_than_the_remaining_amount_is_rejected(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);
        $branch = $this->makeBranch($tenantA, 'Branch A');
        $staff = $this->makeStaff($tenantA, $branch, 'StaffA3');
        [, $payment] = $this->makePaidOrder($branch, 'ORD-A-3', 50);

        $service = $this->app->make(RefundService::class);
        $service->create($payment, ['amount' => 30, 'reason' => 'First partial refund', 'staff_id' => $staff->id]);

        $this->expectException(ValidationException::class);
        $service->create($payment, ['amount' => 30, 'reason' => 'Second refund exceeds remaining', 'staff_id' => $staff->id]);
    }
}
