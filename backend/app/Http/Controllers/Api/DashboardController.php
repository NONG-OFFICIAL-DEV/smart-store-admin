<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    // ── Helper: resolve tenant_id from auth user ───────────────────────────────
    private function tenantId(): string
    {
        $user = auth()->user();
        return $user->ownedTenant?->id
            ?? $user->staff()->withoutGlobalScopes()->first()?->tenant_id;
    }

    // ── Helper: date range from period string ──────────────────────────────────
    private function dateRange(string $period): array
    {
        return match (strtolower($period)) {
            'today'  => [Carbon::today(),              Carbon::now()],
            'week'   => [Carbon::now()->startOfWeek(), Carbon::now()],
            'month'  => [Carbon::now()->startOfMonth(), Carbon::now()],
            'year'   => [Carbon::now()->startOfYear(),  Carbon::now()],
            default  => [Carbon::now()->startOfWeek(), Carbon::now()],
        };
    }

    private function prevDateRange(string $period): array
    {
        return match (strtolower($period)) {
            'today'  => [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()],
            'week'   => [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()],
            'month'  => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()],
            'year'   => [Carbon::now()->subYear()->startOfYear(), Carbon::now()->subYear()->endOfYear()],
            default  => [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()],
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/dashboard/stats?period=week
    // ─────────────────────────────────────────────────────────────────────────
    public function stats(Request $request)
    {
        $tenantId = $this->tenantId();
        $period   = $request->input('period', 'week');

        [$from, $to]         = $this->dateRange($period);
        [$prevFrom, $prevTo] = $this->prevDateRange($period);

        $branchIds = Branch::where('tenant_id', $tenantId)->pluck('id');

        // Current period
        $orders = Order::whereIn('branch_id', $branchIds)
            ->whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', ['cancelled']);

        $revenue    = (clone $orders)->sum('total_amount');
        $orderCount = (clone $orders)->count();
        $avgOrder   = $orderCount > 0 ? round($revenue / $orderCount, 2) : 0;

        // Previous period
        $prevOrders = Order::whereIn('branch_id', $branchIds)
            ->whereBetween('created_at', [$prevFrom, $prevTo])
            ->whereNotIn('status', ['cancelled']);

        $prevRevenue    = (clone $prevOrders)->sum('total_amount');
        $prevOrderCount = (clone $prevOrders)->count();
        $prevAvg        = $prevOrderCount > 0 ? round($prevRevenue / $prevOrderCount, 2) : 0;

        $trend = fn($curr, $prev) => $prev > 0
            ? round((($curr - $prev) / $prev) * 100, 1)
            : ($curr > 0 ? 100 : 0);

        $activeProducts = Product::where('tenant_id', $tenantId)
            ->where('is_available', true)->count();

        // Items sold — same period scope as $orders/$prevOrders above.
        $itemsSold = (int) OrderItem::whereHas(
            'order',
            fn($q) =>
            $q->whereIn('branch_id', $branchIds)
                ->whereBetween('created_at', [$from, $to])
                ->whereNotIn('status', ['cancelled'])
        )->sum('quantity');

        $prevItemsSold = (int) OrderItem::whereHas(
            'order',
            fn($q) =>
            $q->whereIn('branch_id', $branchIds)
                ->whereBetween('created_at', [$prevFrom, $prevTo])
                ->whereNotIn('status', ['cancelled'])
        )->sum('quantity');

        // Payment method breakdown — completed payments in the current period.
        $paymentBreakdown = Payment::whereIn('branch_id', $branchIds)
            ->whereBetween('paid_at', [$from, $to])
            ->where('status', 'completed')
            ->select(
                'payment_method',
                DB::raw('SUM(amount) as amount'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('payment_method')
            ->orderByDesc('amount')
            ->get()
            ->map(fn($row) => [
                'method' => $row->payment_method,
                'amount' => (float) $row->amount,
                'count'  => (int) $row->count,
            ]);

        // Branch performance
        $branches = Branch::where('tenant_id', $tenantId)
            ->withCount([
                'orders as today_orders' => fn($q) =>
                $q->whereDate('created_at', today())
                    ->whereNotIn('status', ['cancelled'])
            ])
            ->get()
            ->map(function ($b) use ($branchIds, $from, $to) {
                $branchRevenue = Order::where('branch_id', $b->id)
                    ->whereBetween('created_at', [$from, $to])
                    ->whereNotIn('status', ['cancelled'])
                    ->sum('total_amount');

                $prevRevenue = Order::where('branch_id', $b->id)
                    ->whereBetween('created_at', [
                        Carbon::parse($from)->subWeek(),
                        Carbon::parse($to)->subWeek(),
                    ])
                    ->whereNotIn('status', ['cancelled'])
                    ->sum('total_amount');

                return [
                    'id'      => $b->id,
                    'name'    => $b->name,
                    'city'    => $b->city ?? $b->address,
                    'revenue' => $branchRevenue,
                    'orders'  => $b->today_orders,
                    'growth'  => $prevRevenue > 0
                        ? round((($branchRevenue - $prevRevenue) / $prevRevenue) * 100, 1)
                        : 0,
                ];
            })
            ->sortByDesc('revenue')
            ->values();

        $maxRevenue = $branches->max('revenue') ?: 1;
        $branches = $branches->map(fn($b, $i) => array_merge($b, [
            'rank'           => $i + 1,
            'revenuePercent' => (int) round(($b['revenue'] / $maxRevenue) * 100),
        ]));

        // Orders by type (dine_in / takeaway / delivery)
        $byType = Order::whereIn('branch_id', $branchIds)
            ->whereDate('created_at', today())
            ->whereNotIn('status', ['cancelled'])
            ->select('order_type', DB::raw('count(*) as count'))
            ->groupBy('order_type')
            ->pluck('count', 'order_type');

        return response()->json([
            'success' => true,
            'data'    => [
                'kpis' => [
                    [
                        'label' => 'Total Revenue',
                        'value' => $revenue,
                        'raw'   => $revenue,
                        'isCurrency' => true,
                        'icon'  => 'mdi-cash-multiple',
                        'color' => 'primary',
                        'trend' => $trend($revenue, $prevRevenue),
                    ],
                    [
                        'label' => 'Total Orders',
                        'value' => number_format($orderCount),
                        'isCurrency' => false,
                        'raw'   => $orderCount,
                        'icon'  => 'mdi-receipt-outline',
                        'color' => 'success',
                        'trend' => $trend($orderCount, $prevOrderCount),
                    ],
                    [
                        'label' => 'Avg Order Value',
                        'value' => $avgOrder,
                        'isCurrency' => true,
                        'raw'   => $avgOrder,
                        'icon'  => 'mdi-calculator-variant',
                        'color' => 'warning',
                        'trend' => $trend($avgOrder, $prevAvg),
                    ],
                    [
                        'label' => 'Active Products',
                        'value' => number_format($activeProducts),
                        'raw'   => $activeProducts,
                        'isCurrency' => false,
                        'icon'  => 'mdi-package-variant',
                        'color' => 'secondary',
                        'trend' => 0,
                    ],
                    [
                        'label' => 'Items Sold',
                        'value' => number_format($itemsSold),
                        'raw'   => $itemsSold,
                        'isCurrency' => false,
                        'icon'  => 'mdi-shopping-outline',
                        'color' => 'info',
                        'trend' => $trend($itemsSold, $prevItemsSold),
                    ],
                ],
                'branches'          => $branches->values(),
                'payment_breakdown' => $paymentBreakdown,
                'total_orders_today' => Order::whereIn('branch_id', $branchIds)
                    ->whereDate('created_at', today())
                    ->whereNotIn('status', ['cancelled'])
                    ->count(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/dashboard/chart?period=week&mode=revenue
    // ─────────────────────────────────────────────────────────────────────────
    public function chart(Request $request)
    {
        $tenantId  = $this->tenantId();
        $period    = $request->input('period', 'week');
        $branchIds = Branch::where('tenant_id', $tenantId)->pluck('id');

        [$from, $to] = $this->dateRange($period);

        // ── PostgreSQL: to_char() for date formatting ─────────────────────────
        $groupFormat = match (strtolower($period)) {
            'today' => 'HH24":00"',   // 08:00, 09:00 ...
            'year'  => 'YYYY-MM',     // 2026-01, 2026-02 ...
            default => 'YYYY-MM-DD',  // 2026-03-12 ...
        };

        $rows = Order::whereIn('branch_id', $branchIds)
            ->whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', ['cancelled'])
            ->select(
                DB::raw("to_char(created_at AT TIME ZONE 'UTC', '{$groupFormat}') as label"),
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
                'orders'  => (int)   $r->orders,
            ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/dashboard/live-orders
    // ─────────────────────────────────────────────────────────────────────────
    public function liveOrders()
    {
        $tenantId  = $this->tenantId();
        $branchIds = Branch::where('tenant_id', $tenantId)->pluck('id');

        $orders = Order::with(['branch:id,name', 'items'])
            ->whereIn('branch_id', $branchIds)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderByDesc('created_at')
            ->limit(15)
            ->get()
            ->map(fn($o) => [
                'id'     => $o->id,
                'number' => $o->order_number,
                'branch' => $o->branch?->name,
                'items'  => $o->items->count(),
                'total'  => number_format($o->total_amount, 2),
                'status' => $o->status,
                'ago'    => Carbon::parse($o->created_at)->diffForHumans(),
            ]);

        return response()->json(['success' => true, 'data' => $orders]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/dashboard/top-products?period=week
    // ─────────────────────────────────────────────────────────────────────────
    public function topProducts(Request $request)
    {
        $tenantId  = $this->tenantId();
        $period    = $request->input('period', 'week');
        $branchIds = Branch::where('tenant_id', $tenantId)->pluck('id');

        [$from, $to] = $this->dateRange($period);

        $products = OrderItem::whereHas(
            'order',
            fn($q) =>
            $q->whereIn('branch_id', $branchIds)
                ->whereBetween('created_at', [$from, $to])
                ->whereNotIn('status', ['cancelled'])
        )
            ->select(
                'product_id',
                DB::raw('SUM(quantity) as sold'),
                DB::raw('SUM(total_price) as revenue')  // ✅ was SUM(subtotal)
            )
            ->groupBy('product_id')
            ->orderByDesc('sold')
            ->limit(5)
            ->with('product:id,name,image_url')
            ->get()
            ->map(fn($item) => [
                'name'      => $item->product?->name ?? 'Unknown',
                'image_url' => $item->product?->image_url,
                'sold'      => (int) $item->sold,
                'revenue'   => (float) $item->revenue,
            ]);

        return response()->json(['success' => true, 'data' => $products]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/dashboard/activity
    // ─────────────────────────────────────────────────────────────────────────
    public function activity()
    {
        $tenantId = $this->tenantId();

        $logs = ActivityLog::where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($log) => [
                'id'    => $log->id,
                'title' => $log->description ?? $log->action,
                'desc'  => trim(($log->entity_type ?? '') . ' · ' . ($log->user_name ?? ''), ' · '),
                'color' => match ($log->action) {
                    'created' => 'success',
                    'deleted' => 'error',
                    'updated' => 'primary',
                    default   => 'secondary',
                },
                'time'  => Carbon::parse($log->created_at)->diffForHumans(),
            ]);

        return response()->json(['success' => true, 'data' => $logs]);
    }
}
