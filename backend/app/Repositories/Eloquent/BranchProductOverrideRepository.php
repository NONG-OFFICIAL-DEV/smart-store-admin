<?php

namespace App\Repositories\Eloquent;

use App\Models\BranchProductOverride;
use App\Repositories\Contracts\BranchProductOverrideRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class BranchProductOverrideRepository extends BaseRepository implements BranchProductOverrideRepositoryInterface
{
    public function __construct(BranchProductOverride $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return $this->model->newQuery()->with(['branch', 'product']);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        // No timestamp columns at all on this table (not even a nulled-out
        // one) — BaseRepository's default latest() would 500 on a
        // nonexistent created_at. product_id is the only stable default.
        $query->orderBy($sortBy ?: 'product_id', filter_var($sortDesc, FILTER_VALIDATE_BOOLEAN) ? 'desc' : 'asc');
    }
}
