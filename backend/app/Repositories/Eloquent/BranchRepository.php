<?php

namespace App\Repositories\Eloquent;

use App\Models\Branch;
use App\Repositories\Contracts\BranchRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class BranchRepository extends BaseRepository implements BranchRepositoryInterface
{
    protected array $searchable = ['name', 'city', 'phone'];

    private const ALLOWED_SORTS = ['name', 'created_at', 'is_active', 'city'];

    public function __construct(Branch $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return $this->model->newQuery()->with(['tenant', 'branchType:id,name,code,icon']);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['tenant'])) {
            $query->whereIn('tenant_id', explode(',', $filters['tenant']));
        }

        if (! empty($filters['branch_type'])) {
            $query->whereIn('branch_type_id', explode(',', $filters['branch_type']));
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        if (! in_array($sortBy, self::ALLOWED_SORTS, true)) {
            $query->latest();

            return;
        }

        $direction = filter_var($sortDesc, FILTER_VALIDATE_BOOLEAN) ? 'desc' : 'asc';
        $query->orderBy($sortBy, $direction);
    }
}
