<?php

namespace App\Services;

use App\Models\DailySalesSummary;
use App\Repositories\Contracts\DailySalesSummaryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class DailySalesSummaryService extends BaseService
{
    public function __construct(DailySalesSummaryRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function byBranch(string $branchId, array $filters): LengthAwarePaginator
    {
        return $this->list(array_merge($filters, ['branch_id' => $branchId]));
    }

    // No {branch} segment in the URL for this one (GET reports/sales/{date})
    // — a tenant-wide snapshot across every branch for that date, since
    // TenantScope already confines it to the caller's tenant.
    public function forDate(string $date): Collection
    {
        return DailySalesSummary::whereDate('date', $date)->get();
    }

    public function generate(string $branchId, string $date): DailySalesSummary
    {
        return DailySalesSummary::rebuild($branchId, $date);
    }
}
