<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\FloorPlan;
use App\Repositories\Contracts\FloorPlanRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FloorPlanService extends BaseService
{
    public function __construct(FloorPlanRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function byBranch(Branch $branch, array $filters): LengthAwarePaginator
    {
        return $this->list(array_merge($filters, ['branch_id' => $branch->id]));
    }

    public function create(array $data): FloorPlan
    {
        return $this->repository->create($data);
    }

    public function update(FloorPlan $floorPlan, array $data): FloorPlan
    {
        return $this->repository->update($floorPlan, $data);
    }

    public function delete(FloorPlan $floorPlan): bool
    {
        return $this->repository->delete($floorPlan);
    }
}
