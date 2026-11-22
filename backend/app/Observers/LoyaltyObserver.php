<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Services\LoyaltyRewardService;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Auto-credits loyalty points when an order completes, and reverses them
 * when it's later refunded. Two entry points are needed for earning, not
 * just updated(): the immediate-payment POS flows (Mart/Coffee) create the
 * Order already `completed` in one insert (a created() event, no prior
 * state to diff against), while the pay-later settlement path flips an
 * existing pending order to completed via update() (an updated() event).
 * No call site uses raw SQL, so Eloquent events reliably cover both.
 *
 * Refunds only ever reach `updateStatus(..., 'refunded', ...)` (see
 * RefundService) — nothing in the codebase transitions a completed order
 * straight to 'cancelled', so that status needs no reversal handling here.
 */
class LoyaltyObserver
{
    public function __construct(
        private TelegramService $telegram,
        private LoyaltyRewardService $rewards
    ) {
    }

    public function created(Order $order): void
    {
        if ($order->status === 'completed') {
            $this->awardPoints($order);
        }
    }

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        if ($order->status === 'completed') {
            $this->awardPoints($order);
        } elseif ($order->status === 'refunded') {
            $this->reverseAwardedPoints($order);
        }
    }

    private function awardPoints(Order $order): void
    {
        if (! $order->customer_id) {
            return;
        }

        try {
            $customer = $order->customer;
            $tenant = $order->branch?->tenant;

            if (! $customer || ! $tenant) {
                return;
            }

            $settings = LoyaltySetting::forTenant($tenant->id);
            $points = $settings->stampsFor((float) $order->total_amount);

            if ($points <= 0) {
                return;
            }

            $balanceBefore = $customer->loyalty_points;
            $customer->addPoints($points, $order->id);
        } catch (Throwable $e) {
            Log::warning('Loyalty points award failed for order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        // Notification failures shouldn't look like the award itself
        // failed — the points already landed by the time we get here.
        try {
            $this->notifyStampsEarned($customer, $settings, $points);
            $this->notifyIfRewardJustBecameReady($customer, $settings, $balanceBefore, $order->branch_id);
        } catch (Throwable $e) {
            Log::warning('Loyalty notification failed for order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function reverseAwardedPoints(Order $order): void
    {
        if (! $order->customer_id) {
            return;
        }

        try {
            $earned = LoyaltyTransaction::where('order_id', $order->id)->where('type', 'earn')->first();

            if (! $earned) {
                return;
            }

            // Idempotency: a refunded order's status only ever flips to
            // 'refunded' once in practice, but this guards against the
            // observer ever firing twice for the same transition.
            $alreadyReversed = LoyaltyTransaction::where('order_id', $order->id)
                ->where('type', 'adjust')
                ->where('points', '<', 0)
                ->exists();

            if ($alreadyReversed) {
                return;
            }

            $order->customer?->reversePoints($earned->points, $order->id);
        } catch (Throwable $e) {
            Log::warning('Loyalty points reversal failed for refunded order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyStampsEarned(Customer $customer, LoyaltySetting $settings, int $points): void
    {
        if (! $settings->notify_stamps_earned || ! $customer->notify_telegram || ! $customer->telegram_chat_id) {
            return;
        }

        $this->telegram->send(
            $customer->telegram_chat_id,
            "You just earned +{$points} stamp(s)! Your balance is now {$customer->loyalty_points}."
        );
    }

    /**
     * Fires once, right when a reward crosses from "not enough stamps" to
     * "affordable" — not on every earn after that, so a customer sitting on
     * a surplus doesn't get re-pinged on every single visit.
     */
    private function notifyIfRewardJustBecameReady(Customer $customer, LoyaltySetting $settings, int $balanceBefore, ?string $branchId): void
    {
        if (! $settings->notify_reward_ready || ! $customer->notify_telegram || ! $customer->telegram_chat_id) {
            return;
        }

        $justUnlocked = $this->rewards->availableFor($customer->tenant_id, $branchId)
            ->first(fn ($reward) => $reward->points_cost > $balanceBefore && $reward->points_cost <= $customer->loyalty_points);

        if ($justUnlocked) {
            $this->telegram->send(
                $customer->telegram_chat_id,
                "🎁 \"{$justUnlocked->name}\" is ready to redeem — you have enough stamps now!"
            );
        }
    }
}
