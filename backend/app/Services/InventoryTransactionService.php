<?php

namespace App\Services;

use App\Repositories\Contracts\InventoryTransactionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InventoryTransactionService extends BaseService
{
    public function __construct(InventoryTransactionRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function byIngredient(string $ingredientId, array $filters): LengthAwarePaginator
    {
        return $this->list(array_merge($filters, ['ingredient_id' => $ingredientId]));
    }
}
