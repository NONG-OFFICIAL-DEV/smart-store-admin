<?php

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class RoleRepository extends BaseRepository implements RoleRepositoryInterface
{
    protected array $searchable = ['name'];

    public function __construct(Role $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return $this->model->newQuery()->with(['permissions:id,code,group,description']);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (array_key_exists('is_system', $filters) && $filters['is_system'] !== null) {
            $query->where('is_system', filter_var($filters['is_system'], FILTER_VALIDATE_BOOLEAN));
        }
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        // Roles has no meaningful created_at (model disables timestamps) —
        // "name" ascending, not BaseRepository's latest(), is the real default.
        $direction = filter_var($sortDesc, FILTER_VALIDATE_BOOLEAN) ? 'desc' : 'asc';
        $query->orderBy($sortBy ?: 'name', $direction);
    }
}
