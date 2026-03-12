<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Staff;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    private function dateRange(string $period): array
    {
        return match (strtolower($period)) {
            'today' => [Carbon::today(),               Carbon::now()],
            'week'  => [Carbon::now()->startOfWeek(),  Carbon::now()],
            'month' => [Carbon::now()->startOfMonth(),  Carbon::now()],
            'year'  => [Carbon::now()->startOfYear(),   Carbon::now()],
            default => [Carbon::now()->startOfWeek(),  Carbon::now()],
        };
    }

    private function prevDateRange(string $period): array
    {
        return match (strtolower($period)) {
            'today' => [Carbon::yesterday()->startOfDay(),          Carbon::yesterday()->endOfDay()],
            'week'  => [Carbon::now()->subWeek()->startOfWeek(),    Carbon::now()->subWeek()->endOfWeek()],
            'month' => [Carbon::now()->subMonth()->startOfMonth(),  Carbon::now()->subMonth()->endOfMonth()],
            'year'  => [Carbon::now()->subYear()->startOfYear(),    Carbon::now()->subYear()->endOfYear()],
            default => [Carbon::now()->subWeek()->startOfWeek(),    Carbon::now()->subWeek()->endOfWeek()],
        };
    }

    private function trend(float $curr, float $prev): float
    {
        return $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : ($curr > 0 ? 100.0 : 0.0);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/admin/dashboard/stats?period=week
    // ─────────────────────────────────────────────────────────────────────────
    public function stats(Request $request)
    {
        $period              = $request->input('period', 'week');
        [$from, $to]         = $this->dateRange($period);
        [$prevFrom, $prevTo] = $this->prevDateRange($period);

        // ── Platform-wide counts ───────────────────────────────────────────
        $totalTenants   = Tenant::count();
        $activeTenants  = Tenant::where('is_active', true)->count();
        $totalBranches  = Branch::count();
        $totalProducts  = Product::count();
        $totalStaff     = Staff::where('is_active', true)->count();

        $newTenantsThisPeriod = Tenant::whereBetween('created_at', [$from, $to])->count();
        $newTenantsPrev       = Tenant::whereBetween('created_at', [$prevFrom, $prevTo])->count();

        // ── Platform-wide revenue ──────────────────────────────────────────
        $revenue     = Order::whereBetween('created_at', [$from, $to])->whereNotIn('status', ['cancelled'])->sum('total_amount');
        $prevRevenue = Order::whereBetween('created_at', [$prevFrom, $prevTo])->whereNotIn('status', ['cancelled'])->sum('total_amount');

        $orderCount     = Order::whereBetween('created_at', [$from, $to])->whereNotIn('status', ['cancelled'])->count();
        $prevOrderCount = Order::whereBetween('created_at', [$prevFrom, $prevTo])->whereNotIn('status', ['cancelled'])->count();

        // ── Top tenants by revenue ─────────────────────────────────────────
        $topTenants = Tenant::withCount('branches')
            ->get()
            ->map(function ($tenant) use ($from, $to, $prevFrom, $prevTo) {
                $branchIds = Branch::where('tenant_id', $tenant->id)->pluck('id');

                $rev = Order::whereIn('branch_id', $branchIds)
                    ->whereBetween('created_at', [$from, $to])
                    ->whereNotIn('status', ['cancelled'])
                    ->sum('total_amount');

                $prevRev = Order::whereIn('branch_id', $branchIds)
                    ->whereBetween('created_at', [$prevFrom, $prevTo])
                    ->whereNotIn('status', ['cancelled'])
                    ->sum('total_amount');

                $orders = Order::whereIn('branch_id', $branchIds)
                    ->whereDate('created_at', today())
                    ->whereNotIn('status', ['cancelled'])
                    ->count();

                return [
                    'id'             => $tenant->id,
                    'name'           => $tenant->bu_name ?? $tenant->name,
                    'logo_url'       => $tenant->logo_url,
                    'branches_count' => $tenant->branches_count,
                    'revenue'        => (float) $rev,
                    'orders_today'   => $orders,
                    'growth'         => $this->trend($rev, $prevRev),
                    'is_active'      => $tenant->is_active,
                ];
            })
            ->sortByDesc('revenue')
            ->values();

        $maxRev = $topTenants->max('revenue') ?: 1;
        $topTenants = $topTenants->map(fn($t, $i) => array_merge($t, [
            'rank'           => $i + 1,
            'revenuePercent' => (int) round(($t['revenue'] / $maxRev) * 100),
        ]));

        // ── New tenants over time (for chart) ─────────────────────────────
        $recentTenants = Tenant::orderByDesc('created_at')->limit(5)->get()
            ->map(fn($t) => [
                'id'         => $t->id,
                'name'       => $t->bu_name ?? $t->name,
                'logo_url'   => $t->logo_url,
                'created_at' => Carbon::parse($t->created_at)->diffForHumans(),
                'is_active'  => $t->is_active,
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'kpis' => [
                    [
                        'label' => 'Platform Revenue',
                        'value' => '$' . number_format($revenue, 2),
                        'raw'   => $revenue,
                        'icon'  => 'mdi-cash-multiple',
                        'color' => 'primary',
                        'trend' => $this->trend($revenue, $prevRevenue),
                    ],
                    [
                        'label' => 'Total Orders',
                        'value' => number_format($orderCount),
                        'raw'   => $orderCount,
                        'icon'  => 'mdi-receipt-outline',
                        'color' => 'success',
                        'trend' => $this->trend($orderCount, $prevOrderCount),
                    ],
                    [
                        'label' => 'Active Tenants',
                        'value' => $activeTenants . ' / ' . $totalTenants,
                        'raw'   => $activeTenants,
                        'icon'  => 'mdi-domain',
                        'color' => 'warning',
                        'trend' => $this->trend($newTenantsThisPeriod, $newTenantsPrev),
                    ],
                    [
                        'label' => 'Active Branches',
                        'value' => number_format($totalBranches),
                        'raw'   => $totalBranches,
                        'icon'  => 'mdi-store-outline',
                        'color' => 'info',
                        'trend' => 0,
                    ],
                    [
                        'label' => 'Total Staff',
                        'value' => number_format($totalStaff),
                        'raw'   => $totalStaff,
                        'icon'  => 'mdi-account-group-outline',
                        'color' => 'secondary',
                        'trend' => 0,
                    ],
                    [
                        'label' => 'Total Products',
                        'value' => number_format($totalProducts),
                        'raw'   => $totalProducts,
                        'icon'  => 'mdi-package-variant-outline',
                        'color' => 'error',
                        'trend' => 0,
                    ],
                ],
                'top_tenants'    => $topTenants->take(6)->values(),
                'recent_tenants' => $recentTenants,
                'total_orders_today' => Order::whereDate('created_at', today())
                    ->whereNotIn('status', ['cancelled'])->count(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/admin/dashboard/chart?period=week
    // ─────────────────────────────────────────────────────────────────────────
    public function chart(Request $request)
    {
        $period      = $request->input('period', 'week');
        [$from, $to] = $this->dateRange($period);

        // PostgreSQL TO_CHAR format mapping
        $groupFormat = match (strtolower($period)) {
            'today' => 'HH24:00',     // 24-hour format
            'year'  => 'YYYY-MM',     // Year-Month
            default => 'YYYY-MM-DD',  // Year-Month-Day
        };

        $rows = Order::whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', ['cancelled'])
            ->select(
                // Use TO_CHAR for PostgreSQL instead of DATE_FORMAT
                DB::raw("TO_CHAR(created_at, '{$groupFormat}') as label"),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('label')
            ->orderBy('label')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $rows->map(fn($r) => [
                'label'   => $r->label,
                'revenue' => (float) $r->revenue,
                'orders'  => (int) $r->orders,
            ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/admin/dashboard/tenant-chart?period=week
    // Revenue breakdown per tenant for stacked view
    // ─────────────────────────────────────────────────────────────────────────
    public function tenantChart(Request $request)
    {
        $period      = $request->input('period', 'week');
        [$from, $to] = $this->dateRange($period);

        $tenants = Tenant::where('is_active', true)->get();

        $data = $tenants->map(function ($tenant) use ($from, $to) {
            $branchIds = Branch::where('tenant_id', $tenant->id)->pluck('id');
            $revenue   = Order::whereIn('branch_id', $branchIds)
                ->whereBetween('created_at', [$from, $to])
                ->whereNotIn('status', ['cancelled'])
                ->sum('total_amount');

            return [
                'id'      => $tenant->id,
                'name'    => $tenant->bu_name ?? $tenant->name,
                'revenue' => (float) $revenue,
            ];
        })->sortByDesc('revenue')->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/admin/dashboard/activity
    // ─────────────────────────────────────────────────────────────────────────
    public function activity()
    {
        $logs = ActivityLog::with('user:id,first_name,last_name,email')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get()
            ->map(fn($log) => [
                'id'          => $log->id,
                'title'       => $log->description ?? $log->action,
                'desc'        => trim(($log->entity_type ?? '') . ' · ' . ($log->user_name ?? $log->user?->name ?? ''), ' · '),
                'tenant_name' => $log->tenant?->bu_name ?? null,
                'color'       => match ($log->action) {
                    'created' => 'success',
                    'deleted' => 'error',
                    'updated' => 'primary',
                    default   => 'secondary',
                },
                'time'        => Carbon::parse($log->created_at)->diffForHumans(),
            ]);

        return response()->json(['success' => true, 'data' => $logs]);
    }
}
