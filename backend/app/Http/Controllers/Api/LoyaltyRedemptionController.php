<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LoyaltyRedemptionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Staff-facing side of a redemption a customer started in the loyalty-card
 * Mini App — the cashier looks the code up (scan or manual entry) and
 * confirms it at checkout. Points were already deducted when the customer
 * requested it (see LoyaltyRedemptionService); this just marks it handed
 * over.
 */
class LoyaltyRedemptionController extends Controller
{
    use ApiResponse;

    public function __construct(private LoyaltyRedemptionService $redemptions)
    {
    }

    public function show(string $code): JsonResponse
    {
        $redemption = $this->redemptions->findByCode($code);

        if (! $redemption) {
            return $this->error('Redemption code not found.', 404, [], 'NOT_FOUND');
        }

        return $this->success($this->format($redemption));
    }

    public function confirm(Request $request, string $code): JsonResponse
    {
        $redemption = $this->redemptions->findByCode($code);

        if (! $redemption) {
            return $this->error('Redemption code not found.', 404, [], 'NOT_FOUND');
        }

        $request->validate([
            'order_id' => ['nullable', 'uuid', 'exists:orders,id'],
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
        ]);

        try {
            $redemption = $this->redemptions->confirm(
                $redemption,
                auth()->user(),
                $request->input('order_id'),
                $request->input('branch_id')
            );
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422, [], 'REDEMPTION_NOT_PENDING');
        }

        return $this->success($this->format($redemption), 'Reward redeemed.');
    }

    private function format($redemption): array
    {
        return [
            'code' => $redemption->code,
            'status' => $redemption->status,
            'expires_at' => $redemption->expires_at,
            'redeemed_at' => $redemption->redeemed_at,
            'order_id' => $redemption->order_id,
            'branch_id' => $redemption->branch_id,
            'customer' => [
                'id' => $redemption->customer->id,
                'name' => trim("{$redemption->customer->first_name} {$redemption->customer->last_name}"),
            ],
            'reward' => [
                'id' => $redemption->reward->id,
                'name' => $redemption->reward->name,
                'points_cost' => $redemption->reward->points_cost,
            ],
            'discount' => $this->redemptions->discountInstruction($redemption->reward),
        ];
    }
}
