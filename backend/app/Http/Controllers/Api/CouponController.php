<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use App\Models\Promotion;
use App\Services\CouponService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    use ApiResponse;

    public function __construct(private CouponService $coupons)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->coupons->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'is_active',
        ]));

        return $this->success(
            CouponResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function byPromotion(Request $request, Promotion $promotion): JsonResponse
    {
        $paginator = $this->coupons->byPromotion($promotion, $request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'is_active',
        ]));

        return $this->success(
            CouponResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        $coupon = $this->coupons->create($request->validated());

        return $this->created(new CouponResource($coupon), 'Coupon created successfully.');
    }

    public function show(Coupon $coupon): JsonResponse
    {
        return $this->success(new CouponResource($coupon->load('promotion')));
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): JsonResponse
    {
        $coupon = $this->coupons->update($coupon, $request->validated());

        return $this->success(new CouponResource($coupon), 'Coupon updated successfully.');
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $this->coupons->delete($coupon);

        return $this->noContent('Coupon deleted successfully.');
    }
}
