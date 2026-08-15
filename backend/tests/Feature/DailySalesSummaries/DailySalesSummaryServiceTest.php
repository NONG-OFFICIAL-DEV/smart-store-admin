<?php

namespace Tests\Feature\DailySalesSummaries;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DailySalesSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * DailySalesSummaryController::store/show/update/destroy were empty stubs,
 * and 6 routed methods (byBranch, generate, topProducts, topCustomers,
 * revenue, staffReport) didn't exist anywhere. DailySalesSummary::rebuild()
 * had zero callers — this whole table has likely never actually been
 * populated in production. Implemented the well-defined pieces
 * (index/show/byBranch/generate, matching rebuild()'s existing
 * branch+date composite design) and re-pointed top-products at the
 * already-working DashboardController::topProducts() instead of
 * duplicating it. topCustomers/revenue/staffReport had no implementation
 * anywhere to migrate — removed those routes rather than invent new
 * analytics logic with no spec; see CLAUDE.md.
 */
class DailySalesSummaryServiceTest extends TestCase
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

    public function test_generate_rebuilds_the_summary_from_completed_orders(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);

        Order::create([
            'branch_id' => $branch->id, 'order_number' => 'ORD-1', 'total_amount' => 50,
            'status' => 'completed', 'order_type' => 'dine_in',
        ]);
        Order::create([
            'branch_id' => $branch->id, 'order_number' => 'ORD-2', 'total_amount' => 30,
            'status' => 'pending', 'order_type' => 'takeaway',
        ]);

        $service = $this->app->make(DailySalesSummaryService::class);
        $summary = $service->generate($branch->id, now()->toDateString());

        // Only the completed order counts.
        $this->assertSame(1, $summary->total_orders);
        $this->assertSame('50.00', $summary->total_revenue);
    }

    public function test_forDate_returns_every_branchs_summary_for_that_date(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantB');
        Auth::login($owner);
        $branchA = Branch::create(['tenant_id' => $tenant->id, 'name' => 'A', 'address_line1' => 'x', 'city' => 'y']);
        $branchB = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);

        $service = $this->app->make(DailySalesSummaryService::class);
        $date = now()->toDateString();
        $service->generate($branchA->id, $date);
        $service->generate($branchB->id, $date);

        $this->assertCount(2, $service->forDate($date));
    }

    public function test_a_tenant_only_sees_their_own_branch_summaries(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantC');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantD');

        Auth::login($ownerB);
        $branchB = Branch::create(['tenant_id' => $tenantB->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        $service = $this->app->make(DailySalesSummaryService::class);
        $service->generate($branchB->id, now()->toDateString());

        Auth::login($ownerA);
        $this->assertSame(0, $service->list([])->total());
    }
}
