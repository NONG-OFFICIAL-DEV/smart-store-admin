<?php

namespace App\Repositories\Eloquent;

use App\Models\CashTransaction;
use App\Repositories\Contracts\CashTransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class CashTransactionRepository extends BaseRepository implements CashTransactionRepositoryInterface
{
    protected array $searchable = ['reason'];

    public function __construct(CashTransaction $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['cash_drawer_id'])) {
            $query->where('cash_drawer_id', $filters['cash_drawer_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
    }
}
