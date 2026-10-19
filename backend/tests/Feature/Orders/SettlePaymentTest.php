<?php

namespace Tests\Feature\Orders;

use App\Http\Controllers\Api\CoffeePOSorderController;
use App\Http\Controllers\Api\OrderController;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Order;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * PATCH orders/{order}/settle-payment — the counterpart to the POS
 * checkout's pay_later option. Collects the payment that was deliberately
 * skipped at checkout and marks the order completed.
 */
class SettlePaymentTest extends TestCase
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

    private function makePayLaterOrder(Tenant $tenant, Branch $branch): Order
    {
        $category = Category::create(['name' => 'Drinks']);
        $product = $this->app->make(ProductService::class)->create([
            'category_id' => $category->id,
            'name' => 'Iced Latte',
            'base_price' => 4.5,
        ], $tenant->id);

        $request = Request::create('/coffee/pos/orders', 'POST', [
            'branch_id' => $branch->id,
            'payment_method' => 'pay_later',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);
        $response = $this->app->make(CoffeePOSorderController::class)->coffeeOrders($request);

        return Order::firstWhere('order_number', $response->getData(true)['data']['order_number']);
    }

    private function settle(Order $order, array $overrides = [])
    {
        $request = Request::create("/orders/{$order->id}/settle-payment", 'PATCH', array_merge([
            'payment_method' => 'cash',
        ], $overrides));

        return $this->app->make(OrderController::class)->settlePayment($request, $order);
    }

    public function test_settling_a_pay_later_order_records_a_payment_and_completes_it(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $order = $this->makePayLaterOrder($tenant, $branch);

        $response = $this->settle($order, ['payment_method' => 'cash', 'cash_tendered' => 10]);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertSame('completed', $data['data']['status']);
        $this->assertSame('cash', $data['data']['payment']['method']);
        $this->assertEquals(10, $data['data']['payment']['cash_tendered']);
        $this->assertEquals(1, $data['data']['payment']['change_given']);

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertNotNull($order->completed_at);
        $this->assertCount(1, $order->payments);
    }

    public function test_settling_rejects_cash_tendered_below_the_order_total(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $order = $this->makePayLaterOrder($tenant, $branch);

        $response = $this->settle($order, ['payment_method' => 'cash', 'cash_tendered' => 1]);

        $this->assertFalse($response->getData(true)['success']);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertCount(0, $order->fresh()->payments);
    }

    public function test_settling_an_order_that_already_has_a_payment_is_rejected(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $order = $this->makePayLaterOrder($tenant, $branch);

        $this->settle($order);
        $response = $this->settle($order);

        $this->assertFalse($response->getData(true)['success']);
        $this->assertCount(1, $order->fresh()->payments);
    }

    private function makeDineInPayLaterOrder(Tenant $tenant, Branch $branch, Table $table): Order
    {
        $category = Category::create(['name' => 'Drinks']);
        $product = $this->app->make(ProductService::class)->create([
            'category_id' => $category->id,
            'name' => 'Iced Latte',
            'base_price' => 4.5,
        ], $tenant->id);

        $this->app->make(CoffeePOSorderController::class)->coffeeOrders(Request::create('/coffee/pos/orders', 'POST', [
            'branch_id' => $branch->id,
            'payment_method' => 'pay_later',
            'order_type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]));

        return Order::where('table_id', $table->id)->where('status', 'pending')->latest()->first();
    }

    public function test_settling_the_only_open_order_at_a_table_frees_the_table(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);
        $order = $this->makeDineInPayLaterOrder($tenant, $branch, $table);

        $this->assertSame(Table::STATUS_OCCUPIED, $table->fresh()->status);

        $this->settle($order);

        $this->assertSame(Table::STATUS_AVAILABLE, $table->fresh()->status);
    }

    // Both orders join the SAME table session (see TableSessionService) —
    // settling either one now pays the whole session at once via
    // paySession(), not just the single order requested. This is the fix
    // for "PAY only pays one order, table stays Occupied."
    public function test_settling_one_order_pays_every_order_in_the_same_session(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);
        $orderOne = $this->makeDineInPayLaterOrder($tenant, $branch, $table);
        $orderTwo = $this->makeDineInPayLaterOrder($tenant, $branch, $table);

        $this->settle($orderOne);

        $this->assertSame('completed', $orderOne->fresh()->status);
        $this->assertSame('completed', $orderTwo->fresh()->status);
        $this->assertCount(1, $orderOne->fresh()->payments);
        $this->assertCount(1, $orderTwo->fresh()->payments);
        $this->assertSame(Table::STATUS_AVAILABLE, $table->fresh()->status);
    }
}
