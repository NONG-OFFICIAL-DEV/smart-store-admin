<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantSubscriptionResource;
use App\Models\PlanBillingCycle;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\TenantSubscriptionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TenantSubscriptionController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TenantSubscriptionService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'status', 'tenant_id', 'plan_id',
        ]));

        return $this->success(
            TenantSubscriptionResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    // Assign a plan to a tenant, or change it if one is already active —
    // both go through the same cancel-existing/create-new service so a
    // tenant's plan only ever changes through one code path.
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => 'required|uuid|exists:tenants,id',
            'plan_id' => 'required|uuid|exists:plans,id',
            'billing_cycle_id' => 'required|uuid|exists:plan_billing_cycles,id',
            'reason' => 'nullable|string|max:255',
        ]);

        // exists() above only checks the ids are real rows in isolation —
        // it doesn't stop a billing_cycle_id belonging to a DIFFERENT plan
        // than the one being assigned.
        $cycleBelongsToPlan = PlanBillingCycle::where('id', $validated['billing_cycle_id'])
            ->where('plan_id', $validated['plan_id'])
            ->exists();

        if (! $cycleBelongsToPlan) {
            return $this->error('The selected billing cycle does not belong to the chosen plan.', 422, [], 'BILLING_CYCLE_PLAN_MISMATCH');
        }

        $tenant = Tenant::findOrFail($validated['tenant_id']);

        try {
            $subscription = $this->service->changePlan(
                tenant: $tenant,
                newPlanId: $validated['plan_id'],
                newCycleId: $validated['billing_cycle_id'],
                changedBy: $request->user()->id,
                reason: $validated['reason'] ?? 'Plan assigned by admin',
            );
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422, [], 'SUBSCRIPTION_CHANGE_INVALID');
        }

        return $this->created(
            new TenantSubscriptionResource($subscription->load(['tenant:id,name,slug', 'plan:id,name,code,price_usd'])),
            'Subscription saved successfully.'
        );
    }

    public function show(TenantSubscription $subscription): JsonResponse
    {
        return $this->success(new TenantSubscriptionResource(
            $subscription->load(['tenant:id,name,slug', 'plan', 'billingCycle'])
        ));
    }

    public function destroy(TenantSubscription $subscription): JsonResponse
    {
        // Soft-cancel instead of hard delete if active
        if (in_array($subscription->status, ['active', 'trial'])) {
            return $this->error('Cannot delete an active or trial subscription. Cancel it first.', 422, [], 'SUBSCRIPTION_STILL_ACTIVE');
        }

        $subscription->delete();

        return $this->noContent('Subscription deleted successfully.');
    }

    public function toggleActive(Request $request, TenantSubscription $subscription): JsonResponse
    {
        $subscription = $this->service->toggleActive($subscription, $request->user()->id);

        return $this->success(
            new TenantSubscriptionResource($subscription->load(['tenant:id,name,slug', 'plan:id,name,code,price_usd'])),
        );
    }

    public function cancel(Request $request, TenantSubscription $subscription): JsonResponse
    {
        try {
            $subscription = $this->service->cancel($subscription, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422, [], 'SUBSCRIPTION_ALREADY_CANCELLED');
        }

        return $this->success(new TenantSubscriptionResource($subscription), 'Subscription cancelled.');
    }

    public function renew(Request $request, TenantSubscription $subscription): JsonResponse
    {
        try {
            $subscription = $this->service->renew($subscription, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422, [], 'SUBSCRIPTION_NOT_RENEWABLE');
        }

        return $this->success(
            new TenantSubscriptionResource($subscription->load(['tenant:id,name,slug', 'plan:id,name,code,price_usd'])),
            'Subscription renewed successfully.'
        );
    }
}
