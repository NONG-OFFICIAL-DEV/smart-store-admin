<?php

namespace Tests\Feature\Tables;

use App\Http\Controllers\Api\CoffeePOSorderController;
use App\Http\Controllers\Api\OrderController;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Order;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductService;
use App\Services\TableSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Phase 1 of the order/table/payment redesign: a table's repeat dine-in
 * orders now join one shared TableSession instead of staying as separate,
 * un-grouped Order rows. See TableSessionService for the actual rules.
 */
class TableSessionServiceTest extends TestCase
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

    private function makeProduct(string $tenantId, float $price = 2.5): \App\Models\Product
    {
        $category = Category::create(['name' => 'Drinks']);

        return $this->app->make(ProductService::class)->create([
            'category_id' => $category->id,
            'name' => 'Coca',
            'base_price' => $price,
        ], $tenantId);
    }

    private function placeDineInOrder(Branch $branch, $product, Table $table, int $qty = 1, string $paymentMethod = 'pay_later'): Order
    {
        $response = $this->app->make(CoffeePOSorderController::class)->coffeeOrders(Request::create('/coffee/pos/orders', 'POST', [
            'branch_id' => $branch->id,
            'payment_method' => $paymentMethod,
            'order_type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => $qty],
            ],
        ]));

        // Two orders placed back-to-back can land in the same second —
        // latest()->first() would tie-break arbitrarily, so fetch by the
        // id the response actually just gave us instead.
        return Order::find($response->getData(true)['data']['id']);
    }

    public function test_placing_a_dine_in_order_opens_a_session_and_occupies_the_table(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);

        $order = $this->placeDineInOrder($branch, $product, $table);

        $this->assertNotNull($order->table_session_id);
        $this->assertSame(TableSession::STATUS_OPEN, TableSession::find($order->table_session_id)->status);
        $this->assertSame(Table::STATUS_OCCUPIED, $table->fresh()->status);
    }

    public function test_a_second_order_at_the_same_table_joins_the_same_session(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);

        $first = $this->placeDineInOrder($branch, $product, $table);
        $second = $this->placeDineInOrder($branch, $product, $table);

        $this->assertSame($first->table_session_id, $second->table_session_id);
        $this->assertSame(1, TableSession::where('table_id', $table->id)->count());
    }

    public function test_session_bill_merges_items_by_product_variant_and_price(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id, 2.5);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);

        $first = $this->placeDineInOrder($branch, $product, $table, 1);
        $this->placeDineInOrder($branch, $product, $table, 1);

        $bill = $this->app->make(TableSessionService::class)->getSessionBill($first->table_session_id);

        $this->assertCount(1, $bill['items']);
        $this->assertSame(2, $bill['items'][0]['quantity']);
        $this->assertSame(2, $bill['order_count']);
    }

    public function test_session_bill_excludes_cancelled_orders(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);

        $order = $this->placeDineInOrder($branch, $product, $table);
        $order->update(['status' => 'cancelled']);

        $bill = $this->app->make(TableSessionService::class)->getSessionBill($order->table_session_id);

        $this->assertSame(0, $bill['order_count']);
        $this->assertCount(0, $bill['items']);
    }

    public function test_paying_the_only_order_closes_the_session_and_frees_the_table(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);

        $order = $this->placeDineInOrder($branch, $product, $table);

        $this->app->make(OrderController::class)->settlePayment(
            Request::create("/orders/{$order->id}/settle-payment", 'PATCH', ['payment_method' => 'cash']),
            $order
        );

        $this->assertSame(TableSession::STATUS_CLOSED, TableSession::find($order->table_session_id)->status);
        $this->assertSame(Table::STATUS_AVAILABLE, $table->fresh()->status);
    }

    // Settling any one order in a multi-order session now pays off every
    // order in that session at once (settlePayment() delegates to
    // paySession() whenever the order has a table_session_id) — the fix for
    // "PAY only pays one order."
    public function test_settling_one_order_pays_the_whole_session(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);

        $first = $this->placeDineInOrder($branch, $product, $table);
        $second = $this->placeDineInOrder($branch, $product, $table);

        $this->app->make(OrderController::class)->settlePayment(
            Request::create("/orders/{$first->id}/settle-payment", 'PATCH', ['payment_method' => 'cash']),
            $first
        );

        $this->assertSame(TableSession::STATUS_CLOSED, TableSession::find($first->table_session_id)->status);
        $this->assertSame('completed', $first->fresh()->status);
        $this->assertSame('completed', $second->fresh()->status);
        $this->assertCount(1, $second->fresh()->payments);
        $this->assertSame(Table::STATUS_AVAILABLE, $table->fresh()->status);
    }

    public function test_a_new_order_after_the_session_closes_starts_a_fresh_session(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);

        $first = $this->placeDineInOrder($branch, $product, $table);
        $this->app->make(OrderController::class)->settlePayment(
            Request::create("/orders/{$first->id}/settle-payment", 'PATCH', ['payment_method' => 'cash']),
            $first
        );

        $second = $this->placeDineInOrder($branch, $product, $table);

        $this->assertNotSame($first->table_session_id, $second->table_session_id);
        $this->assertSame(2, TableSession::where('table_id', $table->id)->count());
    }

    public function test_bill_endpoint_returns_null_session_for_an_unoccupied_table(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);

        $response = $this->app->make(OrderController::class)->bill($table);
        $data = $response->getData(true)['data'];

        $this->assertNull($data['session']);
    }

    public function test_bill_endpoint_returns_the_merged_bill_for_an_occupied_table(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);

        $this->placeDineInOrder($branch, $product, $table);

        $response = $this->app->make(OrderController::class)->bill($table);
        $data = $response->getData(true)['data'];

        $this->assertNotNull($data['session']);
        $this->assertSame(1, $data['order_count']);
    }

    public function test_session_system_never_overrides_a_reserved_table(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1', 'status' => Table::STATUS_RESERVED]);

        $order = $this->placeDineInOrder($branch, $product, $table);
        $this->app->make(OrderController::class)->settlePayment(
            Request::create("/orders/{$order->id}/settle-payment", 'PATCH', ['payment_method' => 'cash']),
            $order
        );

        $this->assertSame(Table::STATUS_RESERVED, $table->fresh()->status);
    }
}
