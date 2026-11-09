<?php

namespace Tests\Feature\KitchenTickets;

use App\Http\Controllers\Api\KitchenDisplayTicketController;
use App\Models\Branch;
use App\Models\KitchenDisplayTicket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the fix for the N+1 that used to flood the Kitchen Display /
 * Mobile Orders Carousel with one GET /orders/{id} PER TICKET just to
 * render a card. KitchenDisplayTicketResource now nests a trimmed order
 * summary directly on each ticket (see KitchenDisplayTicketRepository's
 * eager-load), so the frontend never needs that per-ticket follow-up call
 * at all for the list view.
 */
class KitchenDisplayTicketEagerLoadTest extends TestCase
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

    private function makeTicket(Branch $branch, string $orderNumber, ?Table $table = null): KitchenDisplayTicket
    {
        $order = Order::create([
            'branch_id' => $branch->id,
            'order_number' => $orderNumber,
            'status' => 'completed',
            'source' => 'pos',
            'table_id' => $table?->id,
            'total_amount' => 10,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => \App\Models\Product::create([
                'tenant_id' => $branch->tenant_id,
                'category_id' => \App\Models\Category::create(['name' => 'Cat'])->id,
                'name' => 'Iced Latte',
                'base_price' => 4.5,
            ])->id,
            'product_name' => 'Iced Latte',
            'variant_name' => 'Large',
            'quantity' => 2,
            'unit_price' => 4.5,
            'total_price' => 9,
            'notes' => 'No sugar',
        ]);

        OrderItemModifier::create([
            'order_item_id' => $item->id,
            'option_name' => 'Oat Milk',
            'price_adjustment' => 0.5,
        ]);

        return KitchenDisplayTicket::create(['order_id' => $order->id, 'branch_id' => $branch->id]);
    }

    public function test_the_ticket_list_response_nests_a_trimmed_order_summary(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);
        $this->makeTicket($branch, 'ORD-1', $table);

        $response = $this->app->make(KitchenDisplayTicketController::class)
            ->index(Request::create('/kitchen-tickets', 'GET', ['branch_id' => $branch->id, 'perPage' => -1]));

        $ticket = $response->getData(true)['data'][0];

        $this->assertSame('ORD-1', $ticket['order']['order_number']);
        $this->assertSame('completed', $ticket['order']['status']);
        $this->assertSame('pos', $ticket['order']['source']);
        $this->assertSame('A1', $ticket['order']['table']['number']);
        $this->assertCount(1, $ticket['order']['items']);
        $this->assertSame(2, $ticket['order']['items'][0]['quantity']);
        $this->assertSame('Iced Latte', $ticket['order']['items'][0]['product_name']);
        $this->assertSame('Large', $ticket['order']['items'][0]['variant_name']);
        $this->assertSame('No sugar', $ticket['order']['items'][0]['notes']);
        $this->assertSame('Oat Milk', $ticket['order']['items'][0]['modifiers'][0]['name']);
    }

    private function countQueriesForTicketList(Branch $branch): int
    {
        // flushQueryLog() — DB::disableQueryLog() does NOT clear the log
        // array, so a second measurement without an explicit flush would
        // silently accumulate on top of the first and look like an N+1
        // regression that isn't real.
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->app->make(KitchenDisplayTicketController::class)
            ->index(Request::create('/kitchen-tickets', 'GET', ['branch_id' => $branch->id, 'perPage' => -1]));
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_listing_many_tickets_does_not_issue_one_query_per_ticket(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantB');
        $branchA = Branch::create(['tenant_id' => $tenantA->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        Auth::login($ownerA);
        for ($i = 1; $i <= 3; $i++) {
            $this->makeTicket($branchA, "ORD-A{$i}");
        }

        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantC');
        $branchB = Branch::create(['tenant_id' => $tenantB->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        Auth::login($ownerB);
        for ($i = 1; $i <= 20; $i++) {
            $this->makeTicket($branchB, "ORD-B{$i}");
        }

        // Warm-up call first — TenantScope's schema-driven column check is
        // cached 1h per table (see TenantScope's own docs), so an
        // uncounted first hit avoids that one-time cost skewing whichever
        // branch happens to be measured first.
        Auth::login($ownerA);
        $this->countQueriesForTicketList($branchA);

        $queriesForThree = $this->countQueriesForTicketList($branchA);
        Auth::login($ownerB);
        $queriesForTwenty = $this->countQueriesForTicketList($branchB);
        DB::flushQueryLog();

        // The old per-ticket GET /orders/{id} shape would have scaled
        // query count with ticket count (20 tickets » 3 tickets); eager
        // loading keeps it flat regardless of how many tickets are in the
        // list — same fixed handful of queries either way.
        $this->assertSame($queriesForThree, $queriesForTwenty);
    }
}
