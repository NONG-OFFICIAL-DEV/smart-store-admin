<?php

namespace App\Repositories\Eloquent;

use App\Models\TenantSubscription;
use App\Repositories\Contracts\TenantSubscriptionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class TenantSubscriptionRepository extends BaseRepository implements TenantSubscriptionRepositoryInterface
{
    // status is an enum, not free text — no useful ilike column on this
    // table itself. Search instead goes through the tenant's name (see
    // applySearch() override below) since that's what the action table's
    // search box is actually meant to find.
    protected array $searchable = [];

    public function __construct(TenantSubscription $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return $this->model->newQuery()->with([
            'tenant:id,name,slug',
            'plan:id,name,code,price_usd',
            'billingCycle:id,plan_id,label,months,discount_percent',
        ]);
    }

    protected function applySearch(Builder $query, ?string $term): void
    {
        if (! $term) {
            return;
        }

        $query->whereHas('tenant', fn (Builder $q) => $q->where('name', 'ilike', "%{$term}%"));
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (! empty($filters['plan_id'])) {
            $query->where('plan_id', $filters['plan_id']);
        }
    }
}
