<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePromotionRequest;
use App\Http\Requests\UpdatePromotionRequest;
use App\Http\Resources\PromotionResource;
use App\Models\Promotion;
use App\Services\PromotionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    use ApiResponse;

    public function __construct(private PromotionService $promotions)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->promotions->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'is_active',
        ]));

        return $this->success(
            PromotionResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StorePromotionRequest $request): JsonResponse
    {
        $promotion = $this->promotions->create($request->validated(), $request);

        return $this->created(new PromotionResource($promotion), 'Promotion created successfully.');
    }

    public function show(Promotion $promotion): JsonResponse
    {
        return $this->success(new PromotionResource($promotion));
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): JsonResponse
    {
        $promotion = $this->promotions->update($promotion, $request->validated());

        return $this->success(new PromotionResource($promotion), 'Promotion updated successfully.');
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        $this->promotions->delete($promotion);

        return $this->noContent('Promotion deleted successfully.');
    }
}
