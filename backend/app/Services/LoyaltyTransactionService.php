<?php

namespace App\Services;

use App\Repositories\Contracts\LoyaltyTransactionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LoyaltyTransactionService extends BaseService
{
    public function __construct(LoyaltyTransactionRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function byCustomer(string $customerId, array $filters): LengthAwarePaginator
    {
        return $this->list(array_merge($filters, ['customer_id' => $customerId]));
    }
}
