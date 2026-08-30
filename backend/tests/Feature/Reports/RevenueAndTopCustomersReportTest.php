<?php

namespace Tests\Feature\Reports;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DailySalesSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * revenue()/topCustomers() query Order directly (no OrderRepository exists
 * project-wide) — see DailySalesSummaryService for the aggregation logic.
 */
class RevenueAndTopCustomersReportTest extends TestCase
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

    private function makeOrder(Branch $branch, string $status, float $total, ?string $customerId = null, ?string $date = null): Order
    {
        $order = Order::create([
            'branch_id' => $branch->id,
            'order_number' => 'ORD-'.uniqid(),
            'status' => $status,
            'order_type' => 'dine_in',
            'total_amount' => $total,
            'customer_id' => $customerId,
        ]);

        if ($date) {
            $order->forceFill(['created_at' => $date])->save();
        }

        return $order;
    }

    public function test_revenue_sums_completed_orders_in_range_and_excludes_cancelled_and_out_of_range(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);

        $this->makeOrder($branch, 'completed', 100, date: '2026-03-10 10:00:00');
        $this->makeOrder($branch, 'completed', 50, date: '2026-03-15 10:00:00');
        $this->makeOrder($branch, 'cancelled', 999, date: '2026-03-12 10:00:00');
        $this->makeOrder($branch, 'completed', 500, date: '2026-01-01 10:00:00');

        $result = $this->app->make(DailySalesSummaryService::class)->revenue([
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]);

        $this->assertSame('150.00', $result['summary']['total_revenue']);
        $this->assertSame(2, $result['summary']['total_orders']);
        $this->assertCount(2, $result['daily']);
    }

    public function test_revenue_branch_filter_narrows_to_that_branch_only(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantB');
        Auth::login($owner);
        $branchA = Branch::create(['tenant_id' => $tenant->id, 'name' => 'A', 'address_line1' => 'x', 'city' => 'y']);
        $branchB = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);

        $this->makeOrder($branchA, 'completed', 100, date: '2026-03-10 10:00:00');
        $this->makeOrder($branchB, 'completed', 40, date: '2026-03-10 10:00:00');

        $result = $this->app->make(DailySalesSummaryService::class)->revenue([
            'branch_id' => $branchA->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]);

        $this->assertSame('100.00', $result['summary']['total_revenue']);
        $this->assertSame(1, $result['summary']['total_orders']);
    }

    public function test_top_customers_ranks_by_total_spent_and_excludes_walk_ins(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantC');
        Auth::login($owner);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);

        $bigSpender = Customer::create(['tenant_id' => $tenant->id, 'first_name' => 'Big', 'last_name' => 'Spender']);
        $smallSpender = Customer::create(['tenant_id' => $tenant->id, 'first_name' => 'Small', 'last_name' => 'Spender']);

        $this->makeOrder($branch, 'completed', 300, $bigSpender->id, '2026-03-05 10:00:00');
        $this->makeOrder($branch, 'completed', 50, $smallSpender->id, '2026-03-06 10:00:00');
        $this->makeOrder($branch, 'completed', 999, null, '2026-03-07 10:00:00');

        $result = $this->app->make(DailySalesSummaryService::class)->topCustomers([
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]);

        $this->assertCount(2, $result['customers']);
        $this->assertSame($bigSpender->id, $result['customers'][0]->customer_id);
        $this->assertSame($smallSpender->id, $result['customers'][1]->customer_id);
    }

    public function test_top_customers_respects_the_limit(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantD');
        Auth::login($owner);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);

        foreach (range(1, 3) as $i) {
            $customer = Customer::create(['tenant_id' => $tenant->id, 'first_name' => "Customer{$i}", 'last_name' => 'X']);
            $this->makeOrder($branch, 'completed', 10 * $i, $customer->id, '2026-03-05 10:00:00');
        }

        $result = $this->app->make(DailySalesSummaryService::class)->topCustomers([
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'limit' => 2,
        ]);

        $this->assertCount(2, $result['customers']);
    }
}
