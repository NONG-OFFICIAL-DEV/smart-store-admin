<?php

namespace App\Repositories\Eloquent;

use App\Models\DailySalesSummary;
use App\Repositories\Contracts\DailySalesSummaryRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class DailySalesSummaryRepository extends BaseRepository implements DailySalesSummaryRepositoryInterface
{
    public function __construct(DailySalesSummary $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        // Old controller's `search` did `where('date', 'like', "%{$term}%")`
        // — works only by accident (Postgres implicitly casts date to text
        // for LIKE), not a real filter. date_from/date_to is what a date
        // column actually needs.
        if (! empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }
    }

    // Sort by the business date, not row-insertion time (BaseRepository's
    // default latest() sorts by created_at) — `date` is what a reports
    // listing actually means "most recent" by.
    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        parent::applySort($query, $sortBy ?: 'date', $sortDesc !== false ? $sortDesc : true);
    }
}
