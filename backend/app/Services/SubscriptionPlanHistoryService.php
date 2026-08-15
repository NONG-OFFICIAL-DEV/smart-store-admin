<?php

namespace App\Services;

use App\Repositories\Contracts\SubscriptionPlanHistoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SubscriptionPlanHistoryService extends BaseService
{
    public function __construct(SubscriptionPlanHistoryRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }
}
