<?php

namespace App\Services;

use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Repositories\Contracts\ModifierOptionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ModifierOptionService extends BaseService
{
    public function __construct(ModifierOptionRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function byGroup(ModifierGroup $group, array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer(array_merge($filters, ['group_id' => $group->id]));
    }

    public function create(ModifierGroup $group, array $data): ModifierOption
    {
        $data['group_id'] = $group->id;

        return $this->repository->create($data);
    }

    public function update(ModifierOption $option, array $data): ModifierOption
    {
        return $this->repository->update($option, $data);
    }

    public function delete(ModifierOption $option): bool
    {
        return $this->repository->delete($option);
    }
}
