<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanBillingCycle;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\TenantSubscriptionService;
use App\Services\TenantResolver;
use Illuminate\Http\Request;
use InvalidArgumentException;

// Self-service subscription actions for the CALLER'S OWN tenant only.
// Deliberately separate from TenantSubscriptionController (superadmin-only,
// operates on any tenant by id) — these actions never accept a client-
// supplied tenant_id, they always resolve it from the authenticated user.
class BillingController extends Controller
{
    public function __construct(
        private readonly TenantSubscriptionService $service,
        private readonly TenantResolver $tenantResolver,
    ) {
    }

    // ── Change plan (covers both upgrade and downgrade) ─────────────────────
    public function changePlan(Request $request)
    {
        $validated = $request->validate([
            'plan_id'          => 'required|uuid|exists:plans,id',
            'billing_cycle_id' => 'required|uuid|exists:plan_billing_cycles,id',
        ]);

        // The exists() rules above only check the ids are real rows in
        // isolation — they don't stop a billing_cycle_id belonging to a
        // DIFFERENT plan than the one being switched to, which would leave
        // the subscription's plan/cycle mismatched.
        $cycleBelongsToPlan = PlanBillingCycle::where('id', $validated['billing_cycle_id'])
            ->where('plan_id', $validated['plan_id'])
            ->exists();

        if (!$cycleBelongsToPlan) {
            abort(422, 'The selected billing cycle does not belong to the chosen plan.');
        }

        $tenantId = $this->tenantResolver->resolve($request);
        $tenant   = Tenant::withoutGlobalScopes()->findOrFail($tenantId);

        try {
            $subscription = $this->service->changePlan(
                tenant: $tenant,
                newPlanId: $validated['plan_id'],
                newCycleId: $validated['billing_cycle_id'],
                changedBy: $request->user()->id,
                reason: 'Plan changed by tenant owner (self-service)',
                isSelfService: true,
            );
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Plan changed successfully.',
            'data'    => $subscription->load(['plan', 'billingCycle']),
        ], 201);
    }

    // ── Renew (same plan, same billing cycle — extend the current period) ──
    public function renew(Request $request)
    {
        $tenantId = $this->tenantResolver->resolve($request);

        $subscription = TenantSubscription::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest('created_at')
            ->first();

        if (!$subscription) {
            return response()->json(['message' => 'No subscription found for this business.'], 404);
        }

        try {
            $subscription = $this->service->renew($subscription);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription renewed successfully.',
            'data'    => $subscription->load(['plan', 'billingCycle']),
        ]);
    }
}
