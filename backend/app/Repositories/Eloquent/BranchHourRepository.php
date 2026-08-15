<?php

namespace App\Repositories\Eloquent;

use App\Models\BranchHour;
use App\Repositories\Contracts\BranchHourRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class BranchHourRepository extends BaseRepository implements BranchHourRepositoryInterface
{
    public function __construct(BranchHour $model)
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
        // No timestamps on this table at all — day_of_week ascending is the
        // only sensible default (the old default, sort_by=created_at, would
        // error: that column doesn't exist here).
        $query->orderBy('day_of_week', 'asc');
    }
}
