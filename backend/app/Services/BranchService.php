<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Tenant;
use App\Repositories\Contracts\BranchRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        $tenantId = $this->tenantResolver->resolve($request);

        $this->assertBranchLimit($tenantId);

        $data['tenant_id'] = $tenantId;

        return $this->repository->create($data);
    }

    // plans.branches_limit caps how many branches a tenant may create.
    // Null means unlimited (Enterprise). No subscription at all means no
    // data to enforce against, so it's let through rather than blocked on
    // a data gap (e.g. tenants created before this existed).
    private function assertBranchLimit(string $tenantId): void
    {
        $plan = Tenant::find($tenantId)?->activeSubscription?->plan;
        if (! $plan || $plan->branches_limit === null) {
            return;
        }

        $branchCount = Branch::where('tenant_id', $tenantId)->count();

        if ($branchCount >= $plan->branches_limit) {
            throw ValidationException::withMessages([
                'name' => "This plan is limited to {$plan->branches_limit} branches. Upgrade to add more.",
            ]);
        }
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
