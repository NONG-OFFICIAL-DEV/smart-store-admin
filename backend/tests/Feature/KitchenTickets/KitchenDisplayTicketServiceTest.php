<?php

namespace Tests\Feature\KitchenTickets;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\KitchenDisplayTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * KitchenDisplayTicketController::store/show/update/destroy were empty
 * stubs; start/complete had real logic but only as dead (never-routed)
 * static model methods, and the routed `cancel` action didn't exist
 * anywhere at all. All three (start/complete/cancel) are now on the
 * Service.
 */
class KitchenDisplayTicketServiceTest extends TestCase
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

    private function makeOrder(Branch $branch, string $orderNumber): Order
    {
        return Order::create(['branch_id' => $branch->id, 'order_number' => $orderNumber, 'total_amount' => 20]);
    }

    public function test_start_sets_in_progress_and_started_at(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        $order = $this->makeOrder($branch, 'ORD-1');

        $service = $this->app->make(KitchenDisplayTicketService::class);
        $ticket = $service->create(['order_id' => $order->id, 'branch_id' => $branch->id]);
        $started = $service->start($ticket);

        $this->assertSame('in_progress', $started->status);
        $this->assertNotNull($started->started_at);
    }

    public function test_complete_sets_done_and_completed_at(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantB');
        Auth::login($owner);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        $order = $this->makeOrder($branch, 'ORD-2');

        $service = $this->app->make(KitchenDisplayTicketService::class);
        $ticket = $service->create(['order_id' => $order->id, 'branch_id' => $branch->id]);
        $service->start($ticket);
        $completed = $service->complete($ticket);

        $this->assertSame('done', $completed->status);
        $this->assertNotNull($completed->completed_at);
        $this->assertIsInt($completed->prep_time_minutes);
    }

    public function test_cancel_sets_cancelled(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantC');
        Auth::login($owner);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        $order = $this->makeOrder($branch, 'ORD-3');

        $service = $this->app->make(KitchenDisplayTicketService::class);
        $ticket = $service->create(['order_id' => $order->id, 'branch_id' => $branch->id]);
        $cancelled = $service->cancel($ticket);

        $this->assertSame('cancelled', $cancelled->status);
    }

    public function test_a_tenant_only_sees_their_own_tickets(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantD');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantE');

        Auth::login($ownerB);
        $branchB = Branch::create(['tenant_id' => $tenantB->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        $orderB = $this->makeOrder($branchB, 'ORD-4');
        $service = $this->app->make(KitchenDisplayTicketService::class);
        $service->create(['order_id' => $orderB->id, 'branch_id' => $branchB->id]);

        Auth::login($ownerA);
        $this->assertSame(0, $service->list([])->total());
    }
}
