<?php

namespace App\Services;

use App\Models\ModifierGroup;
use App\Repositories\Contracts\ModifierGroupRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ModifierGroupService extends BaseService
{
    public function __construct(
        ModifierGroupRepositoryInterface $repository,
        private TenantResolver $tenantResolver
    ) {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function create(array $data, Request $request): ModifierGroup
    {
        $data['tenant_id'] = $this->tenantResolver->resolve($request);

        return $this->repository->create($data);
    }

    public function update(ModifierGroup $modifierGroup, array $data): ModifierGroup
    {
        return $this->repository->update($modifierGroup, $data);
    }

    public function delete(ModifierGroup $modifierGroup): bool
    {
        return $this->repository->delete($modifierGroup);
    }
}
