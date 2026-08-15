<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchHour;
use App\Repositories\Contracts\BranchHourRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class BranchHourService extends BaseService
{
    public function __construct(BranchHourRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function forBranch(Branch $branch): Collection
    {
        return $this->repository->query()->where('branch_id', $branch->id)->orderBy('day_of_week')->get();
    }

    public function create(Branch $branch, array $data): BranchHour
    {
        $data['branch_id'] = $branch->id;

        return $this->repository->create($data);
    }

    public function update(BranchHour $hour, array $data): BranchHour
    {
        return $this->repository->update($hour, $data);
    }

    public function delete(BranchHour $hour): bool
    {
        return $this->repository->delete($hour);
    }
}
