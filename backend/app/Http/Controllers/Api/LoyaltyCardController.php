<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\LoyaltyCardTokenService;
use App\Services\LoyaltyRewardService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POS-side counterpart to the Mini App's rotating card QR (see
 * LoyaltyMiniAppController::cardToken()) — the cashier scans it to pull up
 * the customer's balance and rewards-ready count at checkout.
 */
class LoyaltyCardController extends Controller
{
    use ApiResponse;

    public function __construct(
        private LoyaltyCardTokenService $cardTokens,
        private LoyaltyRewardService $rewards
    ) {
    }

    public function resolve(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $customerId = $this->cardTokens->resolve($request->input('token'));

        if (! $customerId) {
            return $this->error("This QR code has expired — ask the customer to refresh their card.", 422, [], 'CARD_TOKEN_EXPIRED');
        }

        // TenantScope means a token minted for another tenant's customer
        // resolves to nothing here, not someone else's data — reads as
        // "not this shop" rather than leaking a cross-tenant record.
        $customer = Customer::find($customerId);

        if (! $customer) {
            return $this->error("This card isn't registered at this shop.", 404, [], 'NOT_FOUND');
        }

        return $this->success($this->format($customer, $request->query('branch_id')));
    }

    private function format(Customer $customer, ?string $branchId): array
    {
        $rewardsReady = $this->rewards->availableFor($customer->tenant_id, $branchId)
            ->filter(fn ($reward) => $reward->points_cost <= $customer->loyalty_points)
            ->count();

        return [
            'customer_id' => $customer->id,
            'name' => $customer->full_name,
            'loyalty_points' => $customer->loyalty_points,
            'rewards_ready_count' => $rewardsReady,
        ];
    }
}
