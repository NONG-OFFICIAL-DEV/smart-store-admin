<?php

namespace App\Repositories\Eloquent;

use App\Models\FloorPlan;
use App\Repositories\Contracts\FloorPlanRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class FloorPlanRepository extends BaseRepository implements FloorPlanRepositoryInterface
{
    protected array $searchable = ['name'];

    public function __construct(FloorPlan $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        // No timestamp columns at all — sort_order (then name) is the real default.
        if (! $sortBy) {
            $query->orderBy('sort_order')->orderBy('name');

            return;
        }

        $query->orderBy($sortBy, filter_var($sortDesc, FILTER_VALIDATE_BOOLEAN) ? 'desc' : 'asc');
    }
}
