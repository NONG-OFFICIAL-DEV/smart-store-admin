<?php

namespace App\Services;

use App\Models\BranchProductOverride;
use App\Repositories\Contracts\BranchProductOverrideRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BranchProductOverrideService extends BaseService
{
    public function __construct(BranchProductOverrideRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    // Upsert by branch+product — matches the DB's unique(['branch_id',
    // 'product_id']) constraint and the original model's updateOrCreate.
    public function upsert(array $data): BranchProductOverride
    {
        $override = BranchProductOverride::updateOrCreate(
            ['branch_id' => $data['branch_id'], 'product_id' => $data['product_id']],
            $data
        );

        return $override->load(['branch', 'product']);
    }

    public function update(BranchProductOverride $override, array $data): BranchProductOverride
    {
        $override = $this->repository->update($override, $data);

        return $override->load(['branch', 'product']);
    }

    public function delete(BranchProductOverride $override): bool
    {
        return $this->repository->delete($override);
    }
}
