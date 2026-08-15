<?php

namespace App\Repositories\Eloquent;

use App\Models\LoyaltyTransaction;
use App\Repositories\Contracts\LoyaltyTransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class LoyaltyTransactionRepository extends BaseRepository implements LoyaltyTransactionRepositoryInterface
{
    protected array $searchable = ['type', 'description'];

    public function __construct(LoyaltyTransaction $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
    }
}
