<?php

namespace App\Repositories\Eloquent;

use App\Models\BranchMenu;
use App\Repositories\Contracts\BranchMenuRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class BranchMenuRepository extends BaseRepository implements BranchMenuRepositoryInterface
{
    public function __construct(BranchMenu $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return $this->model->newQuery()->with(['branch', 'menu']);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['menu_id'])) {
            $query->where('menu_id', $filters['menu_id']);
        }
    }
}
