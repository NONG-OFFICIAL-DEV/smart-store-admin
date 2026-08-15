<?php

namespace App\Repositories\Eloquent;

use App\Models\Table;
use App\Repositories\Contracts\TableRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class TableRepository extends BaseRepository implements TableRepositoryInterface
{
    // The old index() searched a `name` column that doesn't exist on this
    // table at all (fields are table_number/status/etc.) — would 500 the
    // instant `search` was passed. table_number is the real analog.
    protected array $searchable = ['table_number', 'status'];

    public function __construct(Table $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['floor_plan_id'])) {
            $query->where('floor_plan_id', $filters['floor_plan_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        // No timestamp columns at all — table_number is the only sensible default.
        $query->orderBy($sortBy ?: 'table_number', filter_var($sortDesc, FILTER_VALIDATE_BOOLEAN) ? 'desc' : 'asc');
    }
}
