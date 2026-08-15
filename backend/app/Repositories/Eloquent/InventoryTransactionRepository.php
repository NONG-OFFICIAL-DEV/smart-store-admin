<?php

namespace App\Repositories\Eloquent;

use App\Models\InventoryTransaction;
use App\Repositories\Contracts\InventoryTransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class InventoryTransactionRepository extends BaseRepository implements InventoryTransactionRepositoryInterface
{
    protected array $searchable = ['notes'];

    public function __construct(InventoryTransaction $model)
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

        // Old controller's search filtered `type`/`reference` — neither
        // exists; real columns are transaction_type/reference_type/
        // reference_id.
        if (! empty($filters['transaction_type'])) {
            $query->where('transaction_type', $filters['transaction_type']);
        }
    }
}
