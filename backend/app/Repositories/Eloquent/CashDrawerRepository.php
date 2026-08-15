<?php

namespace App\Repositories\Eloquent;

use App\Models\CashDrawer;
use App\Repositories\Contracts\CashDrawerRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class CashDrawerRepository extends BaseRepository implements CashDrawerRepositoryInterface
{
    protected array $searchable = ['notes'];

    public function __construct(CashDrawer $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['staff_id'])) {
            $query->where('staff_id', $filters['staff_id']);
        }

        // cash_drawers has no `status` column (the old controller's search
        // filtered on one anyway) — an open/closed session is derived from
        // whether closed_at is set.
        if (array_key_exists('is_open', $filters)) {
            filter_var($filters['is_open'], FILTER_VALIDATE_BOOLEAN)
                ? $query->whereNull('closed_at')
                : $query->whereNotNull('closed_at');
        }
    }

    // cash_drawers has no created_at column — BaseRepository's default
    // sort (latest()) would 500 on any listing with no explicit sort.
    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        parent::applySort($query, $sortBy ?: 'opened_at', $sortDesc !== false ? $sortDesc : true);
    }
}
