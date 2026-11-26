<?php

namespace App\Repositories\Eloquent;

use App\Models\CashRegister;
use App\Repositories\Contracts\CashRegisterRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class CashRegisterRepository extends BaseRepository implements CashRegisterRepositoryInterface
{
    protected array $searchable = ['name', 'description'];

    public function __construct(CashRegister $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }
    }
}
