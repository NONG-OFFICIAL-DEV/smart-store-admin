<?php

namespace App\Services;

use App\Models\Promotion;
use App\Repositories\Contracts\PromotionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class PromotionService extends BaseService
{
    public function __construct(
        PromotionRepositoryInterface $repository,
        private TenantResolver $tenantResolver
    ) {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function create(array $data, Request $request): Promotion
    {
        $data['tenant_id'] = $this->tenantResolver->resolve($request);

        return $this->repository->create($data);
    }

    public function update(Promotion $promotion, array $data): Promotion
    {
        return $this->repository->update($promotion, $data);
    }

    public function delete(Promotion $promotion): bool
    {
        return $this->repository->delete($promotion);
    }
}
