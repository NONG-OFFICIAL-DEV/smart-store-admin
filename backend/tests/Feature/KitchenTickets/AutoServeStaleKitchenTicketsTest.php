<?php

namespace Tests\Feature\KitchenTickets;

use App\Http\Controllers\Api\CoffeePOSorderController;
use App\Models\Branch;
use App\Models\Category;
use App\Models\KitchenDisplayTicket;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Covers app/Console/Commands/AutoServeStaleKitchenTickets.php — the safety
 * net for a branch that doesn't actually staff its Kitchen Display: a
 * forgotten ticket (at ANY status — new/in_progress/done) sitting untouched
 * past the age threshold auto-serves instead of accumulating forever (one
 * was seen at 31315 minutes old before this existed).
 */
class AutoServeStaleKitchenTicketsTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithOwner(string $name): array
    {
        $owner = User::create([
            'email' => strtolower($name).'@example.test',
            'first_name' => $name,
            'last_name' => 'Owner',
        ]);

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => strtolower($name).'-'.substr((string) $owner->id, 0, 8),
            'owner_user_id' => $owner->id,
            'business_type_id' => \App\Models\BusinessType::firstWhere('code', 'RESTAURANT')?->id,
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

    private function makeOrderWithTicket(Tenant $tenant, Branch $branch): Order
    {
        $category = Category::create(['name' => 'Drinks']);
        $product = $this->app->make(ProductService::class)->create([
            'category_id' => $category->id,
            'name' => 'Iced Latte',
            'base_price' => 4.5,
        ], $tenant->id);

        // pay_later — this test is about kitchen-ticket auto-serve, not
        // payment; avoids needing an open cash register for a 'cash' sale.
        $request = Request::create('/coffee/pos/orders', 'POST', [
            'branch_id' => $branch->id,
            'payment_method' => 'pay_later',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $response = $this->app->make(CoffeePOSorderController::class)->coffeeOrders($request);

        return Order::firstWhere('order_number', $response->getData(true)['data']['order_number']);
    }

    private function backdateTicket(Order $order, int $hoursAgo): KitchenDisplayTicket
    {
        $ticket = KitchenDisplayTicket::where('order_id', $order->id)->firstOrFail();
        $ticket->forceFill(['created_at' => now()->subHours($hoursAgo)])->save();

        return $ticket->fresh();
    }

    public function test_a_ticket_older_than_the_threshold_gets_auto_served(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $order = $this->makeOrderWithTicket($tenant, $branch);
        $this->backdateTicket($order, 13);

        Artisan::call('kitchen:auto-serve-stale');

        $this->assertSame('served', $order->fresh()->status);
        $this->assertSame(
            1,
            OrderStatusHistory::where('order_id', $order->id)->where('to_status', 'served')->count()
        );
    }

    public function test_a_ticket_still_new_also_gets_auto_served_and_the_ticket_itself_is_marked_done(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $order = $this->makeOrderWithTicket($tenant, $branch);
        $ticket = $this->backdateTicket($order, 24);
        $this->assertSame('new', $ticket->status);

        Artisan::call('kitchen:auto-serve-stale');

        $this->assertSame('served', $order->fresh()->status);
        $this->assertSame('done', $ticket->fresh()->status);
    }

    public function test_a_ticket_younger_than_the_threshold_is_left_alone(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $order = $this->makeOrderWithTicket($tenant, $branch);
        $this->backdateTicket($order, 2);

        Artisan::call('kitchen:auto-serve-stale');

        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_a_branch_configured_with_a_shorter_threshold_auto_serves_sooner(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $branch->update(['kitchen_auto_serve_hours' => 1]);
        $order = $this->makeOrderWithTicket($tenant, $branch);
        $this->backdateTicket($order, 2);

        Artisan::call('kitchen:auto-serve-stale');

        $this->assertSame('served', $order->fresh()->status);
    }

    public function test_a_branch_configured_with_a_longer_threshold_is_not_yet_auto_served(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $branch->update(['kitchen_auto_serve_hours' => 24]);
        $order = $this->makeOrderWithTicket($tenant, $branch);
        $this->backdateTicket($order, 13);

        Artisan::call('kitchen:auto-serve-stale');

        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_the_hours_option_overrides_the_default_threshold(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $order = $this->makeOrderWithTicket($tenant, $branch);
        $this->backdateTicket($order, 3);

        Artisan::call('kitchen:auto-serve-stale', ['--hours' => 1]);

        $this->assertSame('served', $order->fresh()->status);
    }

    public function test_does_not_touch_completed_at(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $order = $this->makeOrderWithTicket($tenant, $branch);
        $order->update(['completed_at' => now()->subHours(13)]);
        $completedAt = $order->fresh()->completed_at;
        $this->backdateTicket($order, 13);

        Artisan::call('kitchen:auto-serve-stale');

        $this->assertNotNull($order->fresh()->completed_at);
        $this->assertTrue($order->fresh()->completed_at->equalTo($completedAt));
    }

    public function test_a_cancelled_order_is_skipped_even_if_its_ticket_is_stale(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $order = $this->makeOrderWithTicket($tenant, $branch);
        $order->update(['status' => 'cancelled']);
        $this->backdateTicket($order, 13);

        Artisan::call('kitchen:auto-serve-stale');

        $this->assertSame('cancelled', $order->fresh()->status);
    }
}
