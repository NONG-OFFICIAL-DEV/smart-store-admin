<?php

namespace Tests\Feature\Tables;

use App\Http\Controllers\Api\CoffeePOSorderController;
use App\Http\Controllers\Api\TableSessionController;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Order;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * PATCH table-sessions/{tableSession}/pay — the fix for "clicking PAY on a
 * table with 2 orders only pays one of them." One click pays off every
 * unpaid order in the session and closes it, in one transaction.
 */
class PaySessionTest extends TestCase
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

    public function test_one_click_pays_every_order_in_the_session_and_closes_it(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $category = Category::create(['name' => 'Drinks']);
        $product = $this->app->make(ProductService::class)->create([
            'category_id' => $category->id,
            'name' => 'Coca',
            'base_price' => 2.5,
        ], $tenant->id);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => '5']);

        $first = $this->placeDineInOrder($branch, $product, $table);
        $second = $this->placeDineInOrder($branch, $product, $table);
        $session = TableSession::find($first->table_session_id);

        $response = $this->app->make(TableSessionController::class)->pay(
            Request::create("/table-sessions/{$session->id}/pay", 'PATCH', ['payment_method' => 'cash', 'cash_tendered' => 10]),
            $session
        );
        $data = $response->getData(true)['data'];

        $this->assertTrue($response->getData(true)['success']);
        $this->assertSame('closed', $data['session']['status']);
        $this->assertSame(2, $data['order_count']);
        $this->assertEquals(5.0, $data['total']);
        $this->assertEquals(10.0, $data['payment']['cash_tendered']);
        $this->assertEquals(5.0, $data['payment']['change_given']);

        $this->assertSame('completed', $first->fresh()->status);
        $this->assertSame('completed', $second->fresh()->status);
        $this->assertCount(1, $first->fresh()->payments);
        $this->assertCount(1, $second->fresh()->payments);
        $this->assertSame(Table::STATUS_AVAILABLE, $table->fresh()->status);
    }

    public function test_merged_items_use_the_same_quantity_merge_as_the_bill(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $category = Category::create(['name' => 'Drinks']);
        $product = $this->app->make(ProductService::class)->create([
            'category_id' => $category->id,
            'name' => 'Coca',
            'base_price' => 2.5,
        ], $tenant->id);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => '5']);

        $first = $this->placeDineInOrder($branch, $product, $table, 1);
        $this->placeDineInOrder($branch, $product, $table, 1);
        $session = TableSession::find($first->table_session_id);

        $response = $this->app->make(TableSessionController::class)->pay(
            Request::create("/x", 'PATCH', ['payment_method' => 'cash']),
            $session
        );
        $data = $response->getData(true)['data'];

        $this->assertCount(1, $data['items']);
        $this->assertSame(2, $data['items'][0]['quantity']);
    }

    public function test_a_second_pay_attempt_on_an_already_closed_session_is_rejected(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $category = Category::create(['name' => 'Drinks']);
        $product = $this->app->make(ProductService::class)->create([
            'category_id' => $category->id,
            'name' => 'Coca',
            'base_price' => 2.5,
        ], $tenant->id);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => '5']);

        $first = $this->placeDineInOrder($branch, $product, $table);
        $session = TableSession::find($first->table_session_id);

        $this->app->make(TableSessionController::class)->pay(
            Request::create('/x', 'PATCH', ['payment_method' => 'cash']),
            $session
        );

        $response = $this->app->make(TableSessionController::class)->pay(
            Request::create('/x', 'PATCH', ['payment_method' => 'cash']),
            $session->fresh()
        );
        $data = $response->getData(true);

        $this->assertFalse($data['success']);
        $this->assertCount(1, $first->fresh()->payments);
    }

    public function test_cash_tendered_below_the_session_total_is_rejected(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $category = Category::create(['name' => 'Drinks']);
        $product = $this->app->make(ProductService::class)->create([
            'category_id' => $category->id,
            'name' => 'Coca',
            'base_price' => 2.5,
        ], $tenant->id);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => '5']);

        $first = $this->placeDineInOrder($branch, $product, $table);
        $session = TableSession::find($first->table_session_id);

        $response = $this->app->make(TableSessionController::class)->pay(
            Request::create('/x', 'PATCH', ['payment_method' => 'cash', 'cash_tendered' => 1]),
            $session
        );
        $data = $response->getData(true);

        $this->assertFalse($data['success']);
        $this->assertCount(0, $first->fresh()->payments);
        $this->assertSame(TableSession::STATUS_OPEN, $session->fresh()->status);
    }
}
