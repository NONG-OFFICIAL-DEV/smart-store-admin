<?php

namespace App\Repositories\Eloquent;

use App\Models\Staff;
use App\Repositories\Contracts\StaffRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class StaffRepository extends BaseRepository implements StaffRepositoryInterface
{
    private const ALLOWED_SORTS = ['created_at', 'hire_date', 'hourly_rate', 'employee_code'];

    public function __construct(Staff $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return $this->model->newQuery()->with(['user', 'role', 'branch']);
    }

    protected function applySearch(Builder $query, ?string $term): void
    {
        if (! $term) {
            return;
        }

        $query->whereHas('user', function (Builder $q) use ($term) {
            $q->where('first_name', 'ilike', "%{$term}%")
                ->orWhere('last_name', 'ilike', "%{$term}%")
                ->orWhere('email', 'ilike', "%{$term}%")
                ->orWhere('phone', 'ilike', "%{$term}%");
        });
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['role_id'])) {
            $query->where('role_id', $filters['role_id']);
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
