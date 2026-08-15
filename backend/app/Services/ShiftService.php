<?php

namespace App\Services;

use App\Models\Shift;
use App\Repositories\Contracts\ShiftRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ShiftService extends BaseService
{
    public function __construct(
        ShiftRepositoryInterface $repository,
        private TenantResolver $tenantResolver
    ) {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function create(array $data, Request $request): Shift
    {
        $data['tenant_id'] = $this->tenantResolver->resolve($request);

        return $this->repository->create($data);
    }

    public function update(Shift $shift, array $data): Shift
    {
        return $this->repository->update($shift, $data);
    }

    public function delete(Shift $shift): bool
    {
        return $this->repository->delete($shift);
    }
}
