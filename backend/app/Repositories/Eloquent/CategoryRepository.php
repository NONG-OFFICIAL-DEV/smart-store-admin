<?php

namespace App\Repositories\Eloquent;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class CategoryRepository extends BaseRepository implements CategoryRepositoryInterface
{
    protected array $searchable = ['name'];

    public function __construct(Category $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return $this->model->newQuery()->with(['tenants', 'businessTypes'])->withCount('products');
    }

    /**
     * Only meaningful from the super-admin "system categories" page — a
     * regular tenant's own list is already homogeneous (their one business
     * type's system categories + their own custom ones), but a super admin
     * sees every category across every tenant, so filtering by business
     * type / system-vs-custom is how they narrow that down.
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['business_type_id'])) {
            $businessTypeId = $filters['business_type_id'];
            $query->whereHas('businessTypes', fn ($q) => $q->where('business_types.id', $businessTypeId));
        }

        if (isset($filters['is_system']) && $filters['is_system'] !== '') {
            $query->where('is_system', filter_var($filters['is_system'], FILTER_VALIDATE_BOOLEAN));
        }
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        // created_at is never populated (model disables timestamps) —
        // sort_order is the resource's actual, deliberately-managed order.
        $query->orderBy($sortBy ?: 'sort_order', filter_var($sortDesc, FILTER_VALIDATE_BOOLEAN) ? 'desc' : 'asc');
    }
}
