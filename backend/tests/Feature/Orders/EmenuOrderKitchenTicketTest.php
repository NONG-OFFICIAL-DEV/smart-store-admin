<?php

namespace Tests\Feature\Orders;

use App\Http\Controllers\Api\OrderController;
use App\Models\BusinessType;
use App\Models\Branch;
use App\Models\Category;
use App\Models\KitchenDisplayTicket;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Public e-menu order placement (POST /v1/public/orders) is the one order
 * source that previously never produced a kitchen ticket at all — staff had
 * to manually look the order up by number from Kitchen Display. Food tenants
 * get a ticket auto-created in the same transaction; mart/retail tenants
 * have no prep step, so none is created for them.
 */
class EmenuOrderKitchenTicketTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(string $businessTypeCode): Tenant
    {
        $owner = User::create([
            'email' => strtolower($businessTypeCode).'@example.test',
            'first_name' => 'Owner',
            'last_name' => $businessTypeCode,
            'is_super_admin' => false,
        ]);

        $businessType = BusinessType::firstWhere('code', $businessTypeCode);

        return Tenant::create([
            'name' => $businessTypeCode.' Tenant',
            'slug' => strtolower($businessTypeCode).'-'.substr((string) $owner->id, 0, 8),
            'owner_user_id' => $owner->id,
            'business_type_id' => $businessType->id,
        ]);
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

    private function makeProduct(Tenant $tenant): Product
    {
        $category = Category::create(['name' => 'Menu Items']);
        $category->tenants()->attach($tenant->id);

        return Product::create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Fried Rice',
            'base_price' => 4.5,
            'is_available' => true,
        ]);
    }

    private function placeOrder(Branch $branch, Product $product)
    {
        $request = Request::create('/v1/public/orders', 'POST', [
            'branch_id' => $branch->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        return $this->app->make(OrderController::class)->store($request);
    }

    public function test_placing_an_order_for_a_food_tenant_auto_creates_a_kitchen_ticket(): void
    {
        $tenant = $this->makeTenant('RESTAURANT');
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant);

        $response = $this->placeOrder($branch, $product);
        $orderId = $response->getData(true)['data']['id'];

        $this->assertSame(
            1,
            KitchenDisplayTicket::where('order_id', $orderId)->count()
        );
    }

    public function test_placing_an_order_for_a_mart_tenant_does_not_create_a_kitchen_ticket(): void
    {
        $tenant = $this->makeTenant('MART');
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant);

        $response = $this->placeOrder($branch, $product);
        $orderId = $response->getData(true)['data']['id'];

        $this->assertSame(
            0,
            KitchenDisplayTicket::where('order_id', $orderId)->count()
        );
    }
}
