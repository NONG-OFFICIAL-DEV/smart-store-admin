<?php

namespace App\Repositories\Eloquent;

use App\Models\Promotion;
use App\Repositories\Contracts\PromotionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class PromotionRepository extends BaseRepository implements PromotionRepositoryInterface
{
    protected array $searchable = ['name', 'type'];

    public function __construct(Promotion $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }
    }
}
