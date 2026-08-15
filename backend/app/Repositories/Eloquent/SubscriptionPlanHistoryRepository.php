<?php

namespace App\Repositories\Eloquent;

use App\Models\SubscriptionPlanHistory;
use App\Repositories\Contracts\SubscriptionPlanHistoryRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionPlanHistoryRepository extends BaseRepository implements SubscriptionPlanHistoryRepositoryInterface
{
    protected array $searchable = ['reason'];

    public function __construct(SubscriptionPlanHistory $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return $this->model->newQuery()->with([
            'fromPlan:id,name,code',
            'toPlan:id,name,code',
            'billingCycle:id,plan_id,label,months',
            'changedByUser:id,first_name,last_name',
        ]);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        // A history timeline reads newest-first by default, not by
        // whatever column BaseRepository's ->latest() falls back to.
        if (! $sortBy) {
            $query->orderBy('changed_at', 'desc');

            return;
        }

        $direction = filter_var($sortDesc, FILTER_VALIDATE_BOOLEAN) ? 'desc' : 'asc';
        $query->orderBy($sortBy, $direction);
    }
}
