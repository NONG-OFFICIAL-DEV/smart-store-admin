<?php

namespace App\Repositories\Eloquent;

use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PermissionRepository extends BaseRepository implements PermissionRepositoryInterface
{
    protected array $searchable = ['code', 'group', 'description'];

    public function __construct(Permission $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['group'])) {
            $query->where('group', $filters['group']);
        }
    }

    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        // created_at is never populated (model disables timestamps on a table
        // with nullable, no-default timestamp columns) — "code" ascending is
        // the only sort that's actually meaningful as a default.
        $direction = filter_var($sortDesc, FILTER_VALIDATE_BOOLEAN) ? 'desc' : 'asc';
        $query->orderBy($sortBy ?: 'code', $direction);
    }

    /**
     * The catalog is small and admin-managed; the page assumes the full set
     * is available client-side (grouping, stats, client-side filtering), so
     * perPage = -1 returns everything instead of being capped like a normal
     * paginated resource. Preserves the original controller's behavior.
     */
    public function paginateServer(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        if ((int) ($filters['perPage'] ?? null) === -1) {
            $query = $this->query();
            $this->applySearch($query, $filters['search'] ?? null);
            $this->applyFilters($query, $filters);
            $this->applySort($query, $filters['sortBy'] ?? null, $filters['sortDesc'] ?? false);

            return $query->paginate(max($query->count(), 1));
        }

        return parent::paginateServer($filters, $perPage);
    }
}
