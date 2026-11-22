<?php

namespace Tests\Feature\Loyalty;

use App\Http\Controllers\Api\MartPosController;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Covers LoyaltyObserver — a completed order with a customer attached
 * should auto-credit loyalty points per the tenant's loyalty_settings
 * (LoyaltySetting::forTenant()), the same way the manual "add points"
 * admin action already does (see CustomerServiceTest).
 */
class LoyaltyAutoEarnTest extends TestCase
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

    public function test_a_completed_sale_credits_the_default_one_point(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);
        $customer = Customer::create(['tenant_id' => $tenant->id, 'first_name' => 'Sok']);

        $response = $this->submit([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $order = Order::firstWhere('order_number', $response->getData(true)['data']['order_number']);
        $this->assertSame('completed', $order->status);

        $customer->refresh();
        $this->assertSame(1, $customer->loyalty_points);

        $transaction = LoyaltyTransaction::where('customer_id', $customer->id)->first();
        $this->assertSame('earn', $transaction->type);
        $this->assertSame(1, $transaction->points);
        $this->assertSame($order->id, $transaction->order_id);
    }

    public function test_a_completed_sale_credits_the_tenants_configured_amount(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantB');
        \App\Models\LoyaltySetting::forTenant($tenant->id)->update(['earning_rate' => 5]);
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);
        $customer = Customer::create(['tenant_id' => $tenant->id, 'first_name' => 'Dara']);

        $this->submit([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $this->assertSame(5, $customer->refresh()->loyalty_points);
    }

    public function test_a_walk_in_sale_with_no_customer_awards_nothing(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantC');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);

        $this->submit([
            'branch_id' => $branch->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $this->assertSame(0, LoyaltyTransaction::count());
    }
}
