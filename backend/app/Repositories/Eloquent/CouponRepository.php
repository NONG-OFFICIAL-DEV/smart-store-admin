<?php

namespace App\Repositories\Eloquent;

use App\Models\Coupon;
use App\Repositories\Contracts\CouponRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class CouponRepository extends BaseRepository implements CouponRepositoryInterface
{
    protected array $searchable = ['code'];

    public function __construct(Coupon $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return parent::query()->with('promotion');
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['promotion_id'])) {
            $query->where('promotion_id', $filters['promotion_id']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }
    }
}
