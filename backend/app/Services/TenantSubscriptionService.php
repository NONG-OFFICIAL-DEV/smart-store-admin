<?php

namespace App\Services;

use App\Models\PlanBillingCycle;
use App\Models\SubscriptionPlanHistory;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Repositories\Contracts\TenantSubscriptionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TenantSubscriptionService extends BaseService
{
    public function __construct(TenantSubscriptionRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ASSIGN / CHANGE PLAN
    //
    //  The ONLY place a tenant's plan is assigned or changed. Works whether
    //  the tenant currently has no subscription (first assignment) or an
    //  existing one (upgrade/downgrade) — both are "cancel whatever's
    //  active, if anything, then start a fresh row for the new plan".
    //
    //  Correct flow:
    //   1. Cancel (set cancelled_at) on the current active/trial subscription
    //      — do NOT delete it or mutate its plan_id in place; invoices and
    //      subscription_plan_history rows are linked to it by id.
    //   2. Create a NEW TenantSubscription row for the new plan.
    //      - If still in trial: carry over remaining trial days.
    //      - If already active (or this is a first assignment): start a
    //        fresh billing period immediately.
    //   3. Append to subscription_plan_history.
    // ──────────────────────────────────────────────────────────────────────────
    public function changePlan(
        Tenant $tenant,
        string $newPlanId,
        ?string $newCycleId,
        string $changedBy,
        string $reason = 'Plan change',
    ): TenantSubscription {
        return DB::transaction(function () use ($tenant, $newPlanId, $newCycleId, $changedBy, $reason) {
            // Lock the row so two concurrent plan-change requests for the same
            // tenant can't both read the same "current" subscription before
            // either commits.
            $current = TenantSubscription::where('tenant_id', $tenant->id)
                ->whereIn('status', ['active', 'trial'])
                ->lockForUpdate()
                ->latest('created_at')
                ->first();

            $fromPlanId = $current?->plan_id;
            // Capture BEFORE cancelling — update() below mutates $current's
            // in-memory status to 'cancelled', so reading it after would
            // always report "not trial".
            $wasTrial    = $current?->status === 'trial';
            $trialEndsAt = $wasTrial ? $current->trial_ends_at : null;
            $cycleId     = $newCycleId ?? $current?->billing_cycle_id;

            if ($current) {
                $current->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            }

            $periodStart = $wasTrial ? null : now();
            $periodEnd   = null;

            if (!$wasTrial) {
                if (!$cycleId) {
                    throw new InvalidArgumentException(
                        'A billing cycle is required to start a non-trial subscription.'
                    );
                }
                $months    = PlanBillingCycle::find($cycleId)?->months ?? 1;
                $periodEnd = now()->addMonths($months);
            }

            $newSubscription = TenantSubscription::create([
                'id'                   => Str::uuid(),
                'tenant_id'            => $tenant->id,
                'plan_id'              => $newPlanId,
                'billing_cycle_id'     => $cycleId,
                'status'               => $wasTrial ? 'trial' : 'active',
                'trial_ends_at'        => $trialEndsAt,
                'current_period_start' => $periodStart,
                'current_period_end'   => $periodEnd,
            ]);

            $this->logHistory($newSubscription, $fromPlanId, $newPlanId, $changedBy, $reason);

            return $newSubscription;
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // RENEW  (same plan, same billing cycle — just extend the period)
    //
    //  Valid for any 'active' subscription — including one whose
    //  current_period_end has already passed. There is no separate
    //  "expired" status in this schema (the enum is only
    //  active|trial|suspended|cancelled): a lapsed-but-unpaid subscription
    //  is still 'active' in the DB, and renewing it back to a valid period
    //  is exactly what this action is for.
    //
    //  Anchor = max(current_period_end, now()):
    //   - Renewing on time or early: anchor is the old period end, so the
    //     billing boundary doesn't shift and no days are lost or gained.
    //   - Renewing late (already expired): anchoring to the stale past
    //     end date could produce a new end date that's STILL in the past
    //     (e.g. 3 months overdue on a monthly cycle => new end = 2 months
    //     ago). Anchoring to "now" instead guarantees one click always
    //     produces a valid, future period.
    // ──────────────────────────────────────────────────────────────────────────
    public function renew(TenantSubscription $subscription, ?string $changedBy = null): TenantSubscription
    {
        return DB::transaction(function () use ($subscription, $changedBy) {
            $subscription = TenantSubscription::where('id', $subscription->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($subscription->status !== 'active') {
                throw new InvalidArgumentException('Only an active subscription can be renewed.');
            }

            $months = PlanBillingCycle::find($subscription->billing_cycle_id)?->months ?? 1;
            $newStart = $subscription->current_period_end && $subscription->current_period_end->isFuture()
                ? $subscription->current_period_end
                : now();

            $subscription->update([
                'current_period_start' => $newStart,
                'current_period_end'   => $newStart->copy()->addMonths($months),
            ]);

            $this->logHistory($subscription, $subscription->plan_id, $subscription->plan_id, $changedBy, 'Renewed');

            return $subscription->fresh();
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CANCEL — sets status + cancelled_at. Distinct from changePlan()'s
    // internal cancel-and-replace step: this is a standalone action with no
    // replacement subscription created.
    // ──────────────────────────────────────────────────────────────────────────
    public function cancel(TenantSubscription $subscription, ?string $changedBy = null): TenantSubscription
    {
        return DB::transaction(function () use ($subscription, $changedBy) {
            $subscription = TenantSubscription::where('id', $subscription->id)->lockForUpdate()->firstOrFail();

            if ($subscription->status === 'cancelled') {
                throw new InvalidArgumentException('Subscription is already cancelled.');
            }

            $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $this->logHistory($subscription, $subscription->plan_id, $subscription->plan_id, $changedBy, 'Cancelled by admin');

            return $subscription->fresh();
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // TOGGLE ACTIVE — flips between 'active' and 'cancelled' only (matches
    // the original controller behavior; 'suspended'/'trial' are handled by
    // other actions, not this toggle).
    // ──────────────────────────────────────────────────────────────────────────
    public function toggleActive(TenantSubscription $subscription, ?string $changedBy = null): TenantSubscription
    {
        return DB::transaction(function () use ($subscription, $changedBy) {
            $subscription = TenantSubscription::where('id', $subscription->id)->lockForUpdate()->firstOrFail();

            if ($subscription->status === 'active') {
                $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
                $reason = 'Cancelled by admin';
            } else {
                $subscription->update(['status' => 'active', 'cancelled_at' => null]);
                $reason = 'Reactivated by admin';
            }

            $this->logHistory($subscription, $subscription->plan_id, $subscription->plan_id, $changedBy, $reason);

            return $subscription->fresh();
        });
    }

    private function logHistory(
        TenantSubscription $subscription,
        ?string $fromPlanId,
        string $toPlanId,
        ?string $changedBy,
        string $reason,
    ): void {
        SubscriptionPlanHistory::create([
            'id'               => Str::uuid(),
            'tenant_id'        => $subscription->tenant_id,
            'from_plan_id'     => $fromPlanId,
            'to_plan_id'       => $toPlanId,
            'billing_cycle_id' => $subscription->billing_cycle_id,
            'changed_by'       => $changedBy,
            'reason'           => $reason,
            'changed_at'       => now(),
        ]);
    }
}
