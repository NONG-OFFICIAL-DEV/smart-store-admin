<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Order;
use App\Repositories\Contracts\BranchRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class BranchService extends BaseService
{
    public function __construct(
        BranchRepositoryInterface $repository,
        private TenantResolver $tenantResolver
    ) {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function create(array $data, Request $request): Branch
    {
        $data['tenant_id'] = $this->tenantResolver->resolve($request);

        return $this->repository->create($data);
    }

    public function update(Branch $branch, array $data): Branch
    {
        return $this->repository->update($branch, $data);
    }

    public function delete(Branch $branch): bool
    {
        return $this->repository->delete($branch);
    }

    public function toggleOpen(Branch $branch): Branch
    {
        $branch->is_open = ! $branch->is_open;
        $branch->save();

        return $branch;
    }

    /**
     * Loads everything the branch detail page needs in one shot — matches
     * the original controller's three-part show() payload (branch + today's
     * order stats + table status summary), just moved out of the controller.
     */
    public function detail(Branch $branch): array
    {
        $branch->load(['tenant.businessType', 'branchType', 'menus', 'staff.user', 'staff.role', 'tables']);

        $today = now()->startOfDay();
        $todayOrders = Order::where('branch_id', $branch->id)
            ->whereDate('created_at', $today)
            ->get();

        $stats = [
            'orders_today' => $todayOrders->count(),
            'revenue_today' => $todayOrders->sum('total_amount'),
            'avg_order' => $todayOrders->count()
                ? round($todayOrders->avg('total_amount'), 2)
                : 0,
        ];

        $tableSummary = [
            'total' => $branch->tables->count(),
            'available' => $branch->tables->where('status', 'available')->count(),
            'occupied' => $branch->tables->where('status', 'occupied')->count(),
            'reserved' => $branch->tables->where('status', 'reserved')->count(),
        ];

        return [
            'branch' => $branch,
            'stats' => $stats,
            'table_summary' => $tableSummary,
        ];
    }
}
