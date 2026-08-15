<?php

namespace App\Services;

use App\Models\BranchType;
use App\Models\BusinessType;
use App\Repositories\Contracts\BusinessTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class BusinessTypeService extends BaseService
{
    public function __construct(BusinessTypeRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function list(): Collection
    {
        return $this->repository->allOrdered();
    }

    public function create(array $data): BusinessType
    {
        return $this->repository->create($data);
    }

    public function update(BusinessType $businessType, array $data): BusinessType
    {
        return $this->repository->update($businessType, $data);
    }

    public function delete(BusinessType $businessType): bool
    {
        return $this->repository->delete($businessType);
    }

    public function branchTypesFor(string $businessTypeId): Collection
    {
        return BranchType::where('business_type_id', $businessTypeId)
            ->where('is_active', true)
            ->orderBy('is_hq', 'desc')
            ->orderBy('sort_order')
            ->get();
    }
}
