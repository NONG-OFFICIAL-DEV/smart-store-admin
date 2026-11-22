<?php

namespace Tests\Feature\Tables;

use App\Http\Controllers\Api\CoffeePOSorderController;
use App\Http\Controllers\Api\TableSessionController;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\LoyaltyReward;
use App\Models\Order;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LoyaltyRedemptionService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * A reward redeemed at the Pay dialog can't rewrite an already-created
 * order's discount_amount — it discounts the session's PAYMENT total
 * instead. See TableSessionService::paySession()'s redemption_code handling.
 */
class PaySessionLoyaltyRedemptionTest extends TestCase
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

    private function placeDineInOrder(Branch $branch, $product, Table $table, int $qty = 1): Order
    {
        $response = $this->app->make(CoffeePOSorderController::class)->coffeeOrders(Request::create('/coffee/pos/orders', 'POST', [
            'branch_id' => $branch->id,
            'payment_method' => 'pay_later',
            'order_type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => $qty],
            ],
        ]));

        return Order::find($response->getData(true)['data']['id']);
    }

    private function setUpSessionWithOrder(string $tenantName, float $price = 10.0): array
    {
        [$tenant, $owner] = $this->makeTenantWithOwner($tenantName);
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $category = Category::create(['name' => 'Drinks']);
        $product = $this->app->make(ProductService::class)->create([
            'category_id' => $category->id,
            'name' => 'Coca',
            'base_price' => $price,
        ], $tenant->id);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => '5']);
        $order = $this->placeDineInOrder($branch, $product, $table);
        $session = TableSession::find($order->table_session_id);

        return [$tenant, $owner, $order, $session];
    }

    private function makeRedemption(Tenant $tenant, array $rewardOverrides = []): array
    {
        $customer = Customer::create(['tenant_id' => $tenant->id, 'first_name' => 'Sok']);
        $customer->addPoints(10, null, 'adjust');
        $reward = LoyaltyReward::create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Test Reward',
            'points_cost' => 10,
            'is_active' => true,
        ], $rewardOverrides));
        $redemption = $this->app->make(LoyaltyRedemptionService::class)->requestRedemption($customer, $reward);

        return [$customer, $reward, $redemption];
    }

    public function test_a_discount_amount_reward_reduces_the_payable_total_and_confirms_the_redemption(): void
    {
        [$tenant, , $order, $session] = $this->setUpSessionWithOrder('TenantA', 10.0);
        [, , $redemption] = $this->makeRedemption($tenant, ['type' => 'discount_amount', 'discount_value' => 3]);

        $response = $this->app->make(TableSessionController::class)->pay(
            Request::create('/x', 'PATCH', [
                'payment_method' => 'cash',
                'cash_tendered' => 10,
                'redemption_code' => $redemption->code,
            ]),
            $session
        );
        $data = $response->getData(true)['data'];

        $this->assertTrue($response->getData(true)['success']);
        $this->assertEquals(3.0, $data['payment']['discount_amount']);
        $this->assertEquals(3.0, $data['payment']['change_given']);

        $payment = $order->fresh()->payments->first();
        $this->assertEquals(7.0, (float) $payment->amount);

        $this->assertSame('redeemed', $redemption->fresh()->status);
        $this->assertSame($order->id, $redemption->fresh()->order_id);
    }

    public function test_a_discount_percent_reward_is_computed_off_the_session_total(): void
    {
        [$tenant, , $order, $session] = $this->setUpSessionWithOrder('TenantA', 20.0);
        [, , $redemption] = $this->makeRedemption($tenant, ['type' => 'discount_percent', 'discount_value' => 10]);

        $response = $this->app->make(TableSessionController::class)->pay(
            Request::create('/x', 'PATCH', ['payment_method' => 'cash', 'redemption_code' => $redemption->code]),
            $session
        );
        $data = $response->getData(true)['data'];

        $this->assertEquals(2.0, $data['payment']['discount_amount']);
        $this->assertEquals(18.0, (float) $order->fresh()->payments->first()->amount);
    }

    public function test_a_free_item_reward_confirms_with_no_numeric_discount(): void
    {
        [$tenant, , $order, $session] = $this->setUpSessionWithOrder('TenantA', 10.0);
        [, , $redemption] = $this->makeRedemption($tenant, ['type' => 'free_item']);

        $response = $this->app->make(TableSessionController::class)->pay(
            Request::create('/x', 'PATCH', ['payment_method' => 'cash', 'redemption_code' => $redemption->code]),
            $session
        );
        $data = $response->getData(true)['data'];

        $this->assertEquals(0.0, $data['payment']['discount_amount']);
        $this->assertEquals(10.0, (float) $order->fresh()->payments->first()->amount);
        $this->assertSame('redeemed', $redemption->fresh()->status);
    }

    public function test_an_already_used_redemption_code_is_rejected(): void
    {
        [$tenant, $owner, , $session] = $this->setUpSessionWithOrder('TenantA', 10.0);
        [, , $redemption] = $this->makeRedemption($tenant, ['type' => 'discount_amount', 'discount_value' => 3]);
        $this->app->make(LoyaltyRedemptionService::class)->confirm($redemption, $owner);

        $response = $this->app->make(TableSessionController::class)->pay(
            Request::create('/x', 'PATCH', ['payment_method' => 'cash', 'redemption_code' => $redemption->code]),
            $session
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('already been used', $response->getData(true)['message']);
    }

    public function test_an_expired_redemption_code_is_rejected(): void
    {
        [$tenant, , , $session] = $this->setUpSessionWithOrder('TenantA', 10.0);
        [, , $redemption] = $this->makeRedemption($tenant, ['type' => 'discount_amount', 'discount_value' => 3]);
        $redemption->update(['expires_at' => now()->subMinute()]);

        $response = $this->app->make(TableSessionController::class)->pay(
            Request::create('/x', 'PATCH', ['payment_method' => 'cash', 'redemption_code' => $redemption->code]),
            $session
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('expired', $response->getData(true)['message']);
    }

    public function test_a_redemption_code_from_another_tenant_is_rejected(): void
    {
        [$tenantB] = $this->makeTenantWithOwner('TenantB');
        [, , $redemptionB] = $this->makeRedemption($tenantB, ['type' => 'discount_amount', 'discount_value' => 3]);

        [, , , $session] = $this->setUpSessionWithOrder('TenantA', 10.0);

        $response = $this->app->make(TableSessionController::class)->pay(
            Request::create('/x', 'PATCH', ['payment_method' => 'cash', 'redemption_code' => $redemptionB->code]),
            $session
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('not valid at this shop', $response->getData(true)['message']);
    }
}
