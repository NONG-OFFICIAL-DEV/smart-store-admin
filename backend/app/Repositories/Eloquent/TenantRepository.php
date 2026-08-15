<?php

namespace App\Repositories\Eloquent;

use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class TenantRepository extends BaseRepository implements TenantRepositoryInterface
{
    protected array $searchable = ['name', 'slug'];

    // Whitelisted to prevent sorting by an arbitrary/unsafe column —
    // preserved verbatim from the original controller.
    private const SORTABLE = ['name', 'created_at', 'is_active'];

    public function __construct(Tenant $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return parent::query()->with([
            'owner:id,first_name,last_name,email',
            'businessType:id,name,code,icon',
            'activeSubscription.plan:id,name,code',
        ]);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['business_type'])) {
            $query->whereIn('business_type_id', explode(',', $filters['business_type']));
        }

        if (! empty($filters['plan'])) {
            $ids = explode(',', $filters['plan']);
            $query->whereHas('activeSubscription', fn($q) => $q->whereIn('plan_id', $ids));
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        $sortBy = in_array($sortBy, self::SORTABLE, true) ? $sortBy : 'created_at';
        parent::applySort($query, $sortBy, $sortDesc !== false ? $sortDesc : true);
    }
}
