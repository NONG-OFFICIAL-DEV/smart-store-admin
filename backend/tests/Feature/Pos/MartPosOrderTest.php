<?php

namespace Tests\Feature\Pos;

use App\Http\Controllers\Api\MartPosController;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Mart POS checkout never accepted customer_id at all — a sale could never
 * attach to a customer, so Loyalty could never accrue. Customer stays
 * optional (walk-in sales must keep working with no customer_id at all).
 */
class MartPosOrderTest extends TestCase
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
        $category = Category::create(['name' => 'Groceries']);

        return $this->app->make(ProductService::class)->create([
            'category_id' => $category->id,
            'name' => 'Rice 5kg',
            'base_price' => 8.0,
            'stock_quantity' => 100,
        ], $tenantId);
    }

    private function submit(array $overrides = []): \Illuminate\Http\JsonResponse
    {
        $request = Request::create('/mart/pos/orders', 'POST', array_merge([
            'payment_method' => 'cash',
        ], $overrides));

        return $this->app->make(MartPosController::class)->storeOrders($request);
    }

    public function test_a_sale_can_attach_to_a_customer(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Sok',
            'last_name' => 'Dara',
        ]);

        $response = $this->submit([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $data = $response->getData(true)['data'];
        $this->assertSame('Sok Dara', $data['customer_name']);

        $order = Order::firstWhere('order_number', $data['order_number']);
        $this->assertSame($customer->id, $order->customer_id);
    }

    public function test_a_walk_in_sale_with_no_customer_still_succeeds(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);

        $response = $this->submit([
            'branch_id' => $branch->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $data = $response->getData(true)['data'];
        $this->assertNull($data['customer_name']);

        $order = Order::firstWhere('order_number', $data['order_number']);
        $this->assertNull($order->customer_id);
    }

    public function test_a_customer_belonging_to_another_tenant_is_rejected(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');
        Auth::login($ownerA);
        $branchA = $this->makeBranch($tenantA);
        $product = $this->makeProduct($tenantA->id);
        $foreignCustomer = Customer::create([
            'tenant_id' => $tenantB->id,
            'first_name' => 'Other',
            'last_name' => 'Tenant',
        ]);

        $this->expectException(HttpResponseException::class);

        $this->submit([
            'branch_id' => $branchA->id,
            'customer_id' => $foreignCustomer->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
    }
}
