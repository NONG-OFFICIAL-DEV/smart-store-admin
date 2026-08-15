<?php

namespace Tests\Feature\Subscriptions;

use App\Models\Plan;
use App\Models\SubscriptionPlanHistory;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\PlanService;
use App\Services\SubscriptionPlanHistoryService;
use App\Services\TenantSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Covers the TenantSubscription resource's migration onto Repository/
 * Service (was previously a raw-query controller with toggleActive()/
 * cancel() mutating the model directly instead of going through
 * TenantSubscriptionService, unlike store()/renew()).
 *
 * Also covers the new "report" side (SubscriptionPlanHistory) — separate
 * from the "action" side (TenantSubscriptionController) per the user's
 * request to split action pages from report pages. Fixed a real gap while
 * building this: only changePlan() ever wrote a history row before —
 * renew()/cancel()/toggleActive() didn't, which would have made a
 * subscription history report silently incomplete.
 */
class TenantSubscriptionServiceTest extends TestCase
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
        ]);

        return [$tenant, $owner];
    }

    private function makePlan(string $code): Plan
    {
        return $this->app->make(PlanService::class)->create([
            'name' => $code,
            'code' => $code,
            'price_usd' => 10,
            'seats' => 1,
            'storage_gb' => 1,
            'billing_cycles' => [
                ['label' => 'Monthly', 'months' => 1, 'discount_percent' => 0],
            ],
        ]);
    }

    public function test_changePlan_creates_a_subscription_and_a_history_row(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        $plan = $this->makePlan('PLANA');
        $cycleId = $plan->billingCycles->first()->id;

        $service = $this->app->make(TenantSubscriptionService::class);
        $subscription = $service->changePlan($tenant, $plan->id, $cycleId, $owner->id, 'Initial assignment');

        $this->assertSame($tenant->id, $subscription->tenant_id);
        $this->assertSame('active', $subscription->status);

        $history = SubscriptionPlanHistory::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($history);
        $this->assertNull($history->from_plan_id);
        $this->assertSame($plan->id, $history->to_plan_id);
        $this->assertSame('Initial assignment', $history->reason);
    }

    public function test_renew_extends_the_period_and_now_logs_history(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantB');
        $plan = $this->makePlan('PLANB');
        $cycleId = $plan->billingCycles->first()->id;

        $service = $this->app->make(TenantSubscriptionService::class);
        $subscription = $service->changePlan($tenant, $plan->id, $cycleId, $owner->id);
        $originalEnd = $subscription->current_period_end;

        $renewed = $service->renew($subscription, $owner->id);

        $this->assertTrue($renewed->current_period_end->isAfter($originalEnd));
        $this->assertSame(2, SubscriptionPlanHistory::where('tenant_id', $tenant->id)->count());
        $this->assertTrue(SubscriptionPlanHistory::where('tenant_id', $tenant->id)->where('reason', 'Renewed')->exists());
    }

    public function test_renew_rejects_a_non_active_subscription(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantC');
        $plan = $this->makePlan('PLANC');
        $cycleId = $plan->billingCycles->first()->id;

        $service = $this->app->make(TenantSubscriptionService::class);
        $subscription = $service->changePlan($tenant, $plan->id, $cycleId, $owner->id);
        $service->cancel($subscription, $owner->id);

        $this->expectException(InvalidArgumentException::class);
        $service->renew($subscription, $owner->id);
    }

    public function test_cancel_sets_status_and_logs_history(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantD');
        $plan = $this->makePlan('PLAND');
        $cycleId = $plan->billingCycles->first()->id;

        $service = $this->app->make(TenantSubscriptionService::class);
        $subscription = $service->changePlan($tenant, $plan->id, $cycleId, $owner->id);

        $cancelled = $service->cancel($subscription, $owner->id);

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertTrue(SubscriptionPlanHistory::where('tenant_id', $tenant->id)->where('reason', 'Cancelled by admin')->exists());
    }

    public function test_cancel_rejects_an_already_cancelled_subscription(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantE');
        $plan = $this->makePlan('PLANE');
        $cycleId = $plan->billingCycles->first()->id;

        $service = $this->app->make(TenantSubscriptionService::class);
        $subscription = $service->changePlan($tenant, $plan->id, $cycleId, $owner->id);
        $service->cancel($subscription, $owner->id);

        $this->expectException(InvalidArgumentException::class);
        $service->cancel($subscription, $owner->id);
    }

    public function test_toggleActive_flips_both_directions_and_logs_each(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantF');
        $plan = $this->makePlan('PLANF');
        $cycleId = $plan->billingCycles->first()->id;

        $service = $this->app->make(TenantSubscriptionService::class);
        $subscription = $service->changePlan($tenant, $plan->id, $cycleId, $owner->id);

        $toggledOff = $service->toggleActive($subscription, $owner->id);
        $this->assertSame('cancelled', $toggledOff->status);

        $toggledOn = $service->toggleActive($toggledOff, $owner->id);
        $this->assertSame('active', $toggledOn->status);
        $this->assertNull($toggledOn->cancelled_at);

        // changePlan (1) + toggle off (1) + toggle on (1)
        $this->assertSame(3, SubscriptionPlanHistory::where('tenant_id', $tenant->id)->count());
    }

    public function test_history_report_only_returns_the_requested_tenants_rows(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantG');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantH');
        $planA = $this->makePlan('PLANG');
        $planB = $this->makePlan('PLANH');

        $service = $this->app->make(TenantSubscriptionService::class);
        $service->changePlan($tenantA, $planA->id, $planA->billingCycles->first()->id, $ownerA->id);
        $service->changePlan($tenantB, $planB->id, $planB->billingCycles->first()->id, $ownerB->id);

        $historyService = $this->app->make(SubscriptionPlanHistoryService::class);
        $results = $historyService->list(['tenant_id' => $tenantA->id]);

        $this->assertSame(1, $results->total());
        $this->assertSame($tenantA->id, $results->first()->tenant_id);
    }

    public function test_list_can_search_by_tenant_name(): void
    {
        [$tenantAlpha, $ownerAlpha] = $this->makeTenantWithOwner('AlphaCo');
        [$tenantBeta, $ownerBeta] = $this->makeTenantWithOwner('BetaCo');
        $plan = $this->makePlan('PLANSEARCH');

        $service = $this->app->make(TenantSubscriptionService::class);
        $service->changePlan($tenantAlpha, $plan->id, $plan->billingCycles->first()->id, $ownerAlpha->id);
        $service->changePlan($tenantBeta, $plan->id, $plan->billingCycles->first()->id, $ownerBeta->id);

        $results = $service->list(['search' => 'Alpha']);

        $this->assertSame(1, $results->total());
        $this->assertSame($tenantAlpha->id, $results->first()->tenant_id);
    }

    public function test_toggleActive_and_cancel_are_scoped_to_the_correct_tenant_subscription(): void
    {
        [$tenant] = $this->makeTenantWithOwner('TenantI');
        $plan = $this->makePlan('PLANI');

        $subscription = TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle_id' => $plan->billingCycles->first()->id,
            'status' => 'active',
        ]);

        $service = $this->app->make(TenantSubscriptionService::class);
        $result = $service->toggleActive($subscription, null);

        $this->assertSame($subscription->id, $result->id);
        $this->assertSame('cancelled', $result->status);
    }
}
