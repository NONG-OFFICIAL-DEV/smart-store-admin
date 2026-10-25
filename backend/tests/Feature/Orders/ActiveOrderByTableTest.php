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
 * GET tables/{table}/active-order (OrderController::byTable) used to exclude
 * 'completed' orders — correct for a running-tab model, but Coffee/Mart POS
 * records a sale as 'completed' the instant it's rung up while the table
 * stays occupied, so a table's just-placed order was never found: Table
 * Management's "tap occupied table -> view order" always came back empty.
 */
class ActiveOrderByTableTest extends TestCase
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

    private function makeProduct(string $tenantId): \App\Models\Product
    {
        $category = Category::create(['name' => 'Drinks']);

        return $this->app->make(ProductService::class)->create([
            'category_id' => $category->id,
            'name' => 'Iced Latte',
            'base_price' => 4.5,
        ], $tenantId);
    }

    public function test_a_completed_coffee_pos_sale_is_still_found_as_the_table_active_order(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);

        $request = Request::create('/coffee/pos/orders', 'POST', [
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'order_type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $this->app->make(CoffeePOSorderController::class)->coffeeOrders($request);

        $order = Order::firstWhere('table_id', $table->id);
        $this->assertSame('completed', $order->status);

        $response = $this->app->make(OrderController::class)->byTable($table->id);
        $data = $response->getData(true)['data'];

        $this->assertNotNull($data);
        $this->assertSame($order->id, $data['id']);
    }

    public function test_a_cancelled_order_is_not_returned_as_the_table_active_order(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);

        Order::create([
            'branch_id' => $branch->id,
            'table_id' => $table->id,
            'order_type' => 'dine_in',
            'status' => 'cancelled',
            'source' => 'pos',
            'subtotal' => 0,
            'tax_amount' => 0,
            'service_charge_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 0,
            'queue_number' => 1,
        ]);

        $response = $this->app->make(OrderController::class)->byTable($table->id);

        $this->assertNull($response->getData(true)['data']);
    }

    public function test_orders_by_table_returns_every_open_order_not_just_the_latest(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);

        // Two separate rounds rung up at the same table, both still unpaid
        // (pay_later) — the app deliberately keeps repeat orders as distinct
        // tickets rather than merging them into one running tab.
        foreach (range(1, 2) as $i) {
            $this->app->make(CoffeePOSorderController::class)->coffeeOrders(Request::create('/coffee/pos/orders', 'POST', [
                'branch_id' => $branch->id,
                'payment_method' => 'pay_later',
                'order_type' => 'dine_in',
                'table_id' => $table->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => $i],
                ],
            ]));
        }

        $response = $this->app->make(OrderController::class)->ordersByTable($table->id);
        $data = $response->getData(true)['data'];

        $this->assertCount(2, $data);
    }

    // Table occupancy no longer closes on payment, so a round paid in full
    // and a brand new unpaid round can coexist in the same open session —
    // staff need to see both (the paid one for history/reference, the
    // unpaid one to collect), not have the paid one silently hidden.
    public function test_orders_by_table_includes_already_paid_orders_in_the_same_sitting(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);

        $this->app->make(CoffeePOSorderController::class)->coffeeOrders(Request::create('/coffee/pos/orders', 'POST', [
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'order_type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]));
        $paidOrder = Order::firstWhere('table_id', $table->id);
        $this->assertSame('completed', $paidOrder->status);

        // A second round rung up at the same table before it's been freed
        // joins the same still-open session.
        $this->app->make(CoffeePOSorderController::class)->coffeeOrders(Request::create('/coffee/pos/orders', 'POST', [
            'branch_id' => $branch->id,
            'payment_method' => 'pay_later',
            'order_type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]));
        $unpaidOrder = Order::where('table_id', $table->id)->where('status', 'pending')->first();

        $response = $this->app->make(OrderController::class)->ordersByTable($table->id);
        $data = collect($response->getData(true)['data'])->keyBy('id');

        $this->assertCount(2, $data);
        $this->assertNotNull($data[$paidOrder->id]['payment']);
        $this->assertNull($data[$unpaidOrder->id]['payment']);
    }

    public function test_orders_by_table_excludes_cancelled_and_refunded_orders(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);
        $session = $this->app->make(\App\Services\TableSessionService::class)->getOrCreateOpenSession($table->id);

        foreach (['cancelled', 'refunded', 'pending'] as $status) {
            Order::create([
                'branch_id' => $branch->id,
                'table_id' => $table->id,
                'table_session_id' => $session->id,
                'order_type' => 'dine_in',
                'status' => $status,
                'source' => 'pos',
                'subtotal' => 0,
                'tax_amount' => 0,
                'service_charge_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 0,
                'queue_number' => 1,
            ]);
        }

        $response = $this->app->make(OrderController::class)->ordersByTable($table->id);
        $data = $response->getData(true)['data'];

        $this->assertCount(1, $data);
        $this->assertSame('pending', $data[0]['status']);
    }

    public function test_orders_by_table_includes_the_table_number(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);

        $this->app->make(CoffeePOSorderController::class)->coffeeOrders(Request::create('/coffee/pos/orders', 'POST', [
            'branch_id' => $branch->id,
            'payment_method' => 'pay_later',
            'order_type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]));

        $response = $this->app->make(OrderController::class)->ordersByTable($table->id);
        $data = $response->getData(true)['data'];

        $this->assertSame('A1', $data[0]['table']['number']);
    }

    public function test_active_order_by_table_includes_the_table_number(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);

        $this->app->make(CoffeePOSorderController::class)->coffeeOrders(Request::create('/coffee/pos/orders', 'POST', [
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'order_type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]));

        $response = $this->app->make(OrderController::class)->byTable($table->id);
        $data = $response->getData(true)['data'];

        $this->assertSame('A1', $data['table']['number']);
    }
}
