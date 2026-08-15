<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Promotion;
use App\Repositories\Contracts\CouponRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class CouponService extends BaseService
{
    public function __construct(CouponRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function byPromotion(Promotion $promotion, array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer(array_merge($filters, ['promotion_id' => $promotion->id]));
    }

    public function create(array $data): Coupon
    {
        // Resolves through Eloquent (TenantScope applies) rather than a raw
        // `exists:promotions,id` validation rule, so a cross-tenant
        // promotion_id 404s instead of silently succeeding.
        Promotion::findOrFail($data['promotion_id']);

        if (empty($data['code'])) {
            $data['code'] = strtoupper(Str::random(8));
        }

        return $this->repository->create($data);
    }

    public function update(Coupon $coupon, array $data): Coupon
    {
        return $this->repository->update($coupon, $data);
    }

    public function delete(Coupon $coupon): bool
    {
        return $this->repository->delete($coupon);
    }
}
