<?php

namespace App\Repositories\Eloquent;

use App\Models\ModifierOption;
use App\Repositories\Contracts\ModifierOptionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class ModifierOptionRepository extends BaseRepository implements ModifierOptionRepositoryInterface
{
    protected array $searchable = ['name'];

    public function __construct(ModifierOption $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['group_id'])) {
            $query->where('group_id', $filters['group_id']);
        }
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        // No timestamp columns at all — sort_order is the resource's
        // actual, deliberately-managed display order.
        $query->orderBy($sortBy ?: 'sort_order', filter_var($sortDesc, FILTER_VALIDATE_BOOLEAN) ? 'desc' : 'asc');
    }
}
