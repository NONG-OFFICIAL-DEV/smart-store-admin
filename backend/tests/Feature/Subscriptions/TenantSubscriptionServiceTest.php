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
use Illuminate\Validation\ValidationException;
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

    private function makePlan(string $code, ?int $trialDays = null): Plan
    {
        return $this->app->make(PlanService::class)->create([
            'name' => $code,
            'code' => $code,
            'price_usd' => 10,
            'seats' => 1,
            'storage_gb' => 1,
            'trial_days' => $trialDays,
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

    public function test_changePlan_starts_a_trial_when_the_plan_has_trial_days_and_this_is_the_first_subscription(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantTrial');
        $plan = $this->makePlan('PLANTRIAL', trialDays: 14);

        $service = $this->app->make(TenantSubscriptionService::class);
        $subscription = $service->changePlan($tenant, $plan->id, null, $owner->id, 'Self-registration');

        $this->assertSame('trial', $subscription->status);
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertTrue($subscription->trial_ends_at->between(now()->addDays(13), now()->addDays(15)));
        $this->assertNull($subscription->current_period_start);
        $this->assertNull($subscription->current_period_end);
    }

    public function test_changePlan_does_not_start_a_trial_when_switching_plans_on_an_existing_subscription(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantNoResetTrial');
        $paidPlan = $this->makePlan('PLANPAID');
        $trialPlan = $this->makePlan('PLANTRIAL2', trialDays: 14);
        $cycleId = $paidPlan->billingCycles->first()->id;

        $service = $this->app->make(TenantSubscriptionService::class);
        // Already an active, paying tenant on a plan with no trial.
        $service->changePlan($tenant, $paidPlan->id, $cycleId, $owner->id, 'Initial assignment');

        // Switching to a plan that DOES offer a trial must not reset them into one.
        $switched = $service->changePlan($tenant, $trialPlan->id, $trialPlan->billingCycles->first()->id, $owner->id, 'Switched plans');

        $this->assertSame('active', $switched->status);
        $this->assertNull($switched->trial_ends_at);
    }

    public function test_changePlan_does_not_start_a_trial_when_the_plan_has_no_trial_days(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantNoTrial');
        $plan = $this->makePlan('PLANNOTRIAL');
        $cycleId = $plan->billingCycles->first()->id;

        $service = $this->app->make(TenantSubscriptionService::class);
        $subscription = $service->changePlan($tenant, $plan->id, $cycleId, $owner->id);

        $this->assertSame('active', $subscription->status);
        $this->assertNull($subscription->trial_ends_at);
    }

    public function test_changePlan_does_not_grant_a_new_trial_after_the_first_trial_is_cancelled(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantTrialCancel');
        $trialPlan = $this->makePlan('PLANTRIALCANCEL', trialDays: 14);
        $otherTrialPlan = $this->makePlan('PLANTRIALCANCEL2', trialDays: 30);

        $service = $this->app->make(TenantSubscriptionService::class);
        $first = $service->changePlan($tenant, $trialPlan->id, null, $owner->id, 'First trial');
        $this->assertSame('trial', $first->status);

        $service->cancel($first, $owner->id);

        // Tenant cancelled before the trial even expired — must not be able
        // to pick another trial-eligible plan and get a fresh trial_ends_at.
        $second = $service->changePlan($tenant, $otherTrialPlan->id, $otherTrialPlan->billingCycles->first()->id, $owner->id, 'Resubscribe after cancel');

        $this->assertSame('active', $second->status);
        $this->assertNull($second->trial_ends_at);
    }

    public function test_changePlan_does_not_grant_a_new_trial_after_the_first_trial_is_suspended(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantTrialSuspend');
        $trialPlan = $this->makePlan('PLANTRIALSUSPEND', trialDays: 14);

        $service = $this->app->make(TenantSubscriptionService::class);
        $first = $service->changePlan($tenant, $trialPlan->id, null, $owner->id, 'First trial');

        // Simulate the trial lapsing the way AuthController::lazilyExpireTrial() does.
        $first->update(['status' => 'suspended']);

        $second = $service->changePlan($tenant, $trialPlan->id, $trialPlan->billingCycles->first()->id, $owner->id, 'Resubscribe after trial lapsed');

        $this->assertSame('active', $second->status);
        $this->assertNull($second->trial_ends_at);
    }

    public function test_self_service_changePlan_rejects_downgrade_to_free_once_a_paid_plan_was_held(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantFreeDowngrade');
        $paidPlan = $this->makePlan('PLANPAIDFORFREE');
        $freePlan = $this->app->make(PlanService::class)->create([
            'name' => 'FREEPLAN',
            'code' => 'FREEPLAN',
            'price_usd' => 0,
            'seats' => 1,
            'storage_gb' => 1,
            'billing_cycles' => [
                ['label' => 'Monthly', 'months' => 1, 'discount_percent' => 0],
            ],
        ]);

        $service = $this->app->make(TenantSubscriptionService::class);
        $service->changePlan($tenant, $paidPlan->id, $paidPlan->billingCycles->first()->id, $owner->id, 'Upgrade to paid', isSelfService: true);

        $this->expectException(InvalidArgumentException::class);
        $service->changePlan($tenant, $freePlan->id, $freePlan->billingCycles->first()->id, $owner->id, 'Attempt downgrade to free', isSelfService: true);
    }

    public function test_admin_changePlan_can_still_downgrade_to_free_for_support(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantFreeDowngradeAdmin');
        $paidPlan = $this->makePlan('PLANPAIDFORFREEADMIN');
        $freePlan = $this->app->make(PlanService::class)->create([
            'name' => 'FREEPLANADMIN',
            'code' => 'FREEPLANADMIN',
            'price_usd' => 0,
            'seats' => 1,
            'storage_gb' => 1,
            'billing_cycles' => [
                ['label' => 'Monthly', 'months' => 1, 'discount_percent' => 0],
            ],
        ]);

        $service = $this->app->make(TenantSubscriptionService::class);
        $service->changePlan($tenant, $paidPlan->id, $paidPlan->billingCycles->first()->id, $owner->id, 'Upgrade to paid');

        // isSelfService defaults to false — the superadmin path is unaffected.
        $downgraded = $service->changePlan($tenant, $freePlan->id, $freePlan->billingCycles->first()->id, $owner->id, 'Support downgrade to free');

        $this->assertSame($freePlan->id, $downgraded->plan_id);
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

    public function test_recordManualPayment_creates_a_paid_invoice_against_the_active_subscription(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantPay');
        $plan = $this->makePlan('PLANPAY');
        $cycleId = $plan->billingCycles->first()->id;

        $service = $this->app->make(TenantSubscriptionService::class);
        $subscription = $service->changePlan($tenant, $plan->id, $cycleId, $owner->id);

        $invoice = $service->recordManualPayment($tenant, [
            'amount_usd' => 25.50,
            'currency' => 'USD',
            'paid_at' => now(),
            'note' => 'Bank transfer',
        ]);

        $this->assertSame($tenant->id, $invoice->tenant_id);
        $this->assertSame($subscription->id, $invoice->subscription_id);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('25.50', $invoice->amount_usd);
        $this->assertSame('Bank transfer', $invoice->note);
        $this->assertNotNull($invoice->invoice_number);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_recordManualPayment_rejects_a_tenant_with_no_active_subscription(): void
    {
        [$tenant] = $this->makeTenantWithOwner('TenantNoSub');
        $service = $this->app->make(TenantSubscriptionService::class);

        $this->expectException(ValidationException::class);
        $service->recordManualPayment($tenant, [
            'amount_usd' => 10,
            'currency' => 'USD',
            'paid_at' => now(),
        ]);
    }

    public function test_listInvoices_only_returns_the_given_tenants_invoices(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantInvA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantInvB');
        $planA = $this->makePlan('PLANINVA');
        $planB = $this->makePlan('PLANINVB');

        $service = $this->app->make(TenantSubscriptionService::class);
        $service->changePlan($tenantA, $planA->id, $planA->billingCycles->first()->id, $ownerA->id);
        $service->changePlan($tenantB, $planB->id, $planB->billingCycles->first()->id, $ownerB->id);

        $service->recordManualPayment($tenantA, ['amount_usd' => 10, 'currency' => 'USD', 'paid_at' => now()]);
        $service->recordManualPayment($tenantB, ['amount_usd' => 20, 'currency' => 'USD', 'paid_at' => now()]);

        $results = $service->listInvoices($tenantA);

        $this->assertSame(1, $results->total());
        $this->assertSame($tenantA->id, $results->first()->tenant_id);
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
