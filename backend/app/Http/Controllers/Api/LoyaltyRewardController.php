<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLoyaltyRewardRequest;
use App\Http\Requests\UpdateLoyaltyRewardRequest;
use App\Http\Resources\LoyaltyRewardResource;
use App\Models\LoyaltyReward;
use App\Services\LoyaltyRewardService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyRewardController extends Controller
{
    use ApiResponse;

    public function __construct(private LoyaltyRewardService $rewards)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->rewards->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'branch_id', 'is_active',
        ]));

        return $this->success(
            LoyaltyRewardResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreLoyaltyRewardRequest $request): JsonResponse
    {
        $reward = $this->rewards->create($request->validated(), $request);

        return $this->created(new LoyaltyRewardResource($reward), 'Reward created successfully.');
    }

    public function show(LoyaltyReward $loyaltyReward): JsonResponse
    {
        return $this->success(new LoyaltyRewardResource($loyaltyReward->load(['branches', 'freeProduct'])));
    }

    public function update(UpdateLoyaltyRewardRequest $request, LoyaltyReward $loyaltyReward): JsonResponse
    {
        $reward = $this->rewards->update($loyaltyReward, $request->validated());

        return $this->success(new LoyaltyRewardResource($reward), 'Reward updated successfully.');
    }

    public function destroy(LoyaltyReward $loyaltyReward): JsonResponse
    {
        $this->rewards->delete($loyaltyReward);

        return $this->noContent('Reward deleted successfully.');
    }
}
