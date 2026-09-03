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
        return $this->model->newQuery()->with(['tenants', 'businessTypes']);
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        // created_at is never populated (model disables timestamps) —
        // sort_order is the resource's actual, deliberately-managed order.
        $query->orderBy($sortBy ?: 'sort_order', filter_var($sortDesc, FILTER_VALIDATE_BOOLEAN) ? 'desc' : 'asc');
    }
}
