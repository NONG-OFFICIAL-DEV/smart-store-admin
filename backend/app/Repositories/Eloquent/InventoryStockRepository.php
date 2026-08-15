<?php

namespace App\Repositories\Eloquent;

use App\Models\InventoryStock;
use App\Repositories\Contracts\InventoryStockRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class InventoryStockRepository extends BaseRepository implements InventoryStockRepositoryInterface
{
    public function __construct(InventoryStock $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['ingredient_id'])) {
            $query->where('ingredient_id', $filters['ingredient_id']);
        }
    }

    // inventory_stock has no created_at column (only updated_at) —
    // BaseRepository's default sort (latest(), which orders by created_at)
    // would 500 on any listing with no explicit sort. The old controller's
    // `search` filtered a nonexistent `location` column, so it's dropped
    // entirely rather than pointed at a real one — there's no free-text
    // column on this table worth searching.
    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        parent::applySort($query, $sortBy ?: 'updated_at', $sortDesc !== false ? $sortDesc : true);
    }
}
