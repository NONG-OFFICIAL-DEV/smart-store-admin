<?php

namespace App\Services;

use App\Models\DailySalesSummary;
use App\Models\Order;
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

    /**
     * Order has no repository (deliberately deferred project-wide), so
     * this queries it directly rather than through the DailySalesSummary
     * repository — same aggregation approach DashboardController already
     * uses, just kept in the Service layer for consistency with this
     * class's other methods. "Revenue" = completed orders' total_amount,
     * matching DailySalesSummary::rebuild()'s own definition (not the
     * looser whereNotIn(['cancelled']) convention used elsewhere).
     */
    public function revenue(array $filters): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $query = fn () => Order::where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->when(! empty($filters['branch_id']), fn ($q) => $q->where('branch_id', $filters['branch_id']));

        $daily = $query()->selectRaw('DATE(created_at) as date, SUM(total_amount) as revenue, COUNT(*) as orders')
            ->groupBy('date')->orderBy('date')->get();

        return [
            'summary' => [
                'total_revenue' => (clone $query())->sum('total_amount'),
                'total_orders' => (clone $query())->count(),
                'avg_order_value' => (clone $query())->avg('total_amount') ?? 0,
            ],
            'daily' => $daily,
        ];
    }

    /**
     * customers.total_spent/total_orders exist as columns but nothing
     * anywhere increments them (no observer, no Order hook) — dead
     * denormalized columns, not a shortcut. Computed fresh from orders.
     * A walk-in order with no customer_id is excluded — there's no
     * customer to rank.
     */
    public function topCustomers(array $filters): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $limit = min((int) ($filters['limit'] ?? 10), 100);

        $customers = Order::where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->when(! empty($filters['branch_id']), fn ($q) => $q->where('branch_id', $filters['branch_id']))
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, COUNT(*) as order_count, SUM(total_amount) as total_spent, AVG(total_amount) as avg_order_value')
            ->groupBy('customer_id')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->with('customer:id,first_name,last_name,email,phone')
            ->get();

        return ['customers' => $customers];
    }

    private function resolveDateRange(array $filters): array
    {
        $from = ($filters['date_from'] ?? now()->startOfMonth()->toDateString()).' 00:00:00';
        $to = ($filters['date_to'] ?? now()->toDateString()).' 23:59:59';

        return [$from, $to];
    }
}
