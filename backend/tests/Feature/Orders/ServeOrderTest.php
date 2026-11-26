<?php

namespace Tests\Feature\Orders;

use App\Http\Controllers\Api\CoffeePOSorderController;
use App\Http\Controllers\Api\OrderController;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * PATCH orders/{order}/serve — a kitchen ticket has no status past 'done'
 * (see KitchenDisplayTicketService), so nothing ever told the ORDER a ready
 * ticket actually reached the customer. This closes that loop: Mobile
 * Orders Carousel / Kitchen Display's "Ready" cards had no action and no
 * way to clear once food was handed off.
 */
class ServeOrderTest extends TestCase
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

    private function makePaidOrder(Tenant $tenant, Branch $branch): Order
    {
        $category = Category::create(['name' => 'Drinks']);
        $product = $this->app->make(ProductService::class)->create([
            'category_id' => $category->id,
            'name' => 'Iced Latte',
            'base_price' => 4.5,
        ], $tenant->id);

        // Cash payments now require an open cash register.
        if (! $this->app->make(\App\Services\CashDrawerService::class)->findOpenForBranch($branch->id)) {
            $role = \App\Models\Role::create(['tenant_id' => $tenant->id, 'name' => 'Cashier Role (fixture)']);
            $user = User::create([
                'email' => 'drawer-'.substr((string) \Illuminate\Support\Str::uuid(), 0, 8).'@example.test',
                'first_name' => 'Drawer', 'last_name' => 'Staff',
            ]);
            $staff = \App\Models\Staff::create([
                'tenant_id' => $tenant->id, 'branch_id' => $branch->id,
                'user_id' => $user->id, 'role_id' => $role->id,
            ]);
            $this->app->make(\App\Services\CashDrawerService::class)->open([
                'branch_id' => $branch->id, 'staff_id' => $staff->id, 'opening_float' => 0,
            ]);
        }

        $request = Request::create('/coffee/pos/orders', 'POST', [
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $response = $this->app->make(CoffeePOSorderController::class)->coffeeOrders($request);

        return Order::firstWhere('order_number', $response->getData(true)['data']['order_number']);
    }

    private function serve(Order $order)
    {
        return $this->app->make(OrderController::class)->serve($order);
    }

    public function test_serving_a_completed_order_marks_it_served_and_logs_history(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $order = $this->makePaidOrder($tenant, $branch);
        $this->assertSame('completed', $order->status);

        $response = $this->serve($order);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertSame('served', $data['data']['status']);

        $order->refresh();
        $this->assertSame('served', $order->status);

        $this->assertSame(
            1,
            OrderStatusHistory::where('order_id', $order->id)
                ->where('from_status', 'completed')
                ->where('to_status', 'served')
                ->count()
        );
    }

    public function test_serving_does_not_wipe_an_already_set_completed_at(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $order = $this->makePaidOrder($tenant, $branch);
        $order->update(['completed_at' => now()]);
        $completedAt = $order->fresh()->completed_at;
        $this->assertNotNull($completedAt);

        $this->serve($order);

        $this->assertNotNull($order->fresh()->completed_at);
        $this->assertTrue($order->fresh()->completed_at->equalTo($completedAt));
    }

    public function test_a_cancelled_order_cannot_be_marked_served(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $order = $this->makePaidOrder($tenant, $branch);
        $order->update(['status' => 'cancelled']);

        $response = $this->serve($order->fresh());
        $data = $response->getData(true);

        $this->assertFalse($data['success']);
        $this->assertSame('cancelled', $order->fresh()->status);
    }
}
