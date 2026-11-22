<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyTransaction;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Points are deducted the moment a customer requests a redemption (not when
 * the cashier confirms it) — avoids a customer stacking several pending
 * redemptions against a balance that can't actually cover all of them.
 * A redemption that expires or is cancelled before the cashier confirms it
 * refunds those points back (a plain `adjust` ledger entry, not a fresh
 * `earn`).
 */
class LoyaltyRedemptionService
{
    private const REDEMPTION_TTL_MINUTES = 10;

    public function requestRedemption(Customer $customer, LoyaltyReward $reward): LoyaltyRedemption
    {
        if ($reward->tenant_id !== $customer->tenant_id || ! $reward->is_active) {
            throw new RuntimeException('Reward not available.');
        }

        if (! $customer->redeemPoints($reward->points_cost)) {
            throw new RuntimeException('Insufficient points.');
        }

        $transactionId = LoyaltyTransaction::where('customer_id', $customer->id)
            ->where('type', 'redeem')
            ->latest('created_at')
            ->value('id');

        return LoyaltyRedemption::create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'loyalty_reward_id' => $reward->id,
            'code' => $this->generateUniqueCode(),
            'status' => 'pending',
            'expires_at' => now()->addMinutes(self::REDEMPTION_TTL_MINUTES),
            'loyalty_transaction_id' => $transactionId,
        ]);
    }

    public function findByCode(string $code): ?LoyaltyRedemption
    {
        $redemption = LoyaltyRedemption::with(['customer', 'reward.branches'])->where('code', $code)->first();

        if ($redemption) {
            $this->expireIfDue($redemption);
        }

        return $redemption;
    }

    /**
     * $orderId/$branchId are optional because they aren't always known yet
     * at confirm time — a Dine-in bill's order already exists by the time
     * the cashier scans a reward, but a Quick Sale order is only created
     * AFTER checkout totals (incl. this discount) are resolved. POS can
     * call discountInstruction() first to fold the reward into the new
     * order's discount_amount, then pass the resulting order_id here.
     */
    public function confirm(LoyaltyRedemption $redemption, User $staff, ?string $orderId = null, ?string $branchId = null): LoyaltyRedemption
    {
        $this->expireIfDue($redemption);

        if ($redemption->status !== 'pending') {
            throw new RuntimeException("Redemption is {$redemption->status}, not pending.");
        }

        $redemption->update([
            'status' => 'redeemed',
            'redeemed_at' => now(),
            'redeemed_by_staff_id' => $staff->id,
            'order_id' => $orderId,
            'branch_id' => $branchId,
        ]);

        return $redemption->refresh();
    }

    /**
     * What POS should actually do to the order/payment total for this
     * reward — a pure lookup, no DB write. `type` is null when the reward
     * was never configured with a monetary value (POS should just mark it
     * used with no price adjustment).
     */
    public function discountInstruction(LoyaltyReward $reward): array
    {
        return match ($reward->type) {
            'discount_percent' => ['type' => 'discount_percent', 'percent' => (float) $reward->discount_value],
            'free_item' => ['type' => 'free_item', 'free_product_id' => $reward->free_product_id],
            'discount_amount' => ['type' => 'discount_amount', 'amount' => (float) $reward->discount_value],
            default => ['type' => null],
        };
    }

    public function cancel(LoyaltyRedemption $redemption): LoyaltyRedemption
    {
        $this->expireIfDue($redemption);

        if ($redemption->status !== 'pending') {
            return $redemption;
        }

        $this->refundPoints($redemption, 'cancelled');

        return $redemption->refresh();
    }

    private function expireIfDue(LoyaltyRedemption $redemption): void
    {
        if ($redemption->isExpired()) {
            $this->refundPoints($redemption, 'expired');
            $redemption->refresh();
        }
    }

    private function refundPoints(LoyaltyRedemption $redemption, string $resultingStatus): void
    {
        $redemption->customer->addPoints($redemption->reward->points_cost, null, 'adjust');
        $redemption->update(['status' => $resultingStatus]);
    }

    private function generateUniqueCode(): string
    {
        // Excludes 0/O/1/I — easy to misread on a receipt-sized screen,
        // and this is meant to be typed in by hand as a QR-scan fallback.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = collect(range(1, 6))
                ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->implode('');
        } while (LoyaltyRedemption::where('code', $code)->exists());

        return $code;
    }
}
