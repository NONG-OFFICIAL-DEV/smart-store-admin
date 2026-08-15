<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function trend(float $curr, float $prev): float
    {
        return $prev > 0
            ? round((($curr - $prev) / $prev) * 100, 1)
            : ($curr > 0 ? 100.0 : 0.0);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/admin/dashboard/stats?period=month
    // ─────────────────────────────────────────────────────────────────────────
    public function stats(Request $request)
    {
        $now       = Carbon::now();
        $thisStart = $now->copy()->startOfMonth();
        $prevStart = $now->copy()->subMonth()->startOfMonth();
        $prevEnd   = $now->copy()->subMonth()->endOfMonth();

        // ── MRR ───────────────────────────────────────────────────────────
        $mrrRow = DB::table('tenant_subscriptions as ts')
            ->join('plans as p', 'p.id', '=', 'ts.plan_id')
            ->leftJoin('plan_billing_cycles as bc', 'bc.id', '=', 'ts.billing_cycle_id')
            ->where('ts.status', 'active')
            ->selectRaw("
                SUM(p.price_usd * (1 - COALESCE(bc.discount_percent, 0) / 100.0)) as mrr
            ")
            ->first();

        $mrr = (float) ($mrrRow->mrr ?? 0);

        // Previous-month MRR approximation
        $prevMrrRow = DB::table('tenant_subscriptions as ts')
            ->join('plans as p', 'p.id', '=', 'ts.plan_id')
            ->leftJoin('plan_billing_cycles as bc', 'bc.id', '=', 'ts.billing_cycle_id')
            ->whereIn('ts.status', ['active', 'cancelled'])
            ->where('ts.current_period_start', '<=', $prevEnd)
            ->where(function ($q) use ($prevStart) {
                $q->whereNull('ts.current_period_end')
                    ->orWhere('ts.current_period_end', '>=', $prevStart);
            })
            ->selectRaw("
                SUM(p.price_usd * (1 - COALESCE(bc.discount_percent, 0) / 100.0)) as mrr
            ")
            ->first();

        $prevMrr = (float) ($prevMrrRow->mrr ?? 0);

        // ── Active tenants ────────────────────────────────────────────────
        $totalTenants  = Tenant::count();
        $activeTenants = Tenant::where('is_active', true)->count();
        $newThisMonth  = Tenant::where('created_at', '>=', $thisStart)->count();
        $newLastMonth  = Tenant::whereBetween('created_at', [$prevStart, $prevEnd])->count();

        // ── Active users ──────────────────────────────────────────────────
        $activeUsers     = User::where('is_active', true)->count();
        $prevActiveUsers = User::where('is_active', true)
            ->where('created_at', '<', $thisStart)
            ->count();

        // ── Churn rate ────────────────────────────────────────────────────
        $cancelledThisMonth    = TenantSubscription::where('status', 'cancelled')
            ->where('cancelled_at', '>=', $thisStart)->count();
        $activeAtMonthStart    = $activeTenants + $cancelledThisMonth;
        $churnRate             = $activeAtMonthStart > 0
            ? round(($cancelledThisMonth / $activeAtMonthStart) * 100, 1) : 0.0;

        $cancelledLastMonth    = TenantSubscription::where('status', 'cancelled')
            ->whereBetween('cancelled_at', [$prevStart, $prevEnd])->count();
        $activeAtPrevStart     = $activeAtMonthStart + $cancelledLastMonth;
        $prevChurnRate         = $activeAtPrevStart > 0
            ? round(($cancelledLastMonth / $activeAtPrevStart) * 100, 1) : 0.0;
        $churnDelta            = round($churnRate - $prevChurnRate, 1);

        // ── Trial → Paid ──────────────────────────────────────────────────
        $convertedThisMonth = TenantSubscription::where('status', 'active')
            ->where('updated_at', '>=', $thisStart)
            ->whereExists(function ($q) use ($thisStart) {
                $q->from('tenant_subscriptions as prev')
                    ->whereColumn('prev.tenant_id', 'tenant_subscriptions.tenant_id')
                    ->where('prev.status', 'trial')
                    ->where('prev.updated_at', '<', $thisStart);
            })
            ->count();

        $trialsThisMonth = TenantSubscription::where('status', 'trial')
            ->where('created_at', '>=', $thisStart)->count();
        $conversionRate  = ($trialsThisMonth + $convertedThisMonth) > 0
            ? round(($convertedThisMonth / ($trialsThisMonth + $convertedThisMonth)) * 100, 1)
            : 0.0;

        // ── Top tenants by MRR contribution ───────────────────────────────
        $topTenants = DB::table('tenants as t')
            ->join('tenant_subscriptions as ts', 'ts.tenant_id', '=', 't.id')
            ->join('plans as p', 'p.id', '=', 'ts.plan_id')
            ->leftJoin('plan_billing_cycles as bc', 'bc.id', '=', 'ts.billing_cycle_id')
            ->select(
                't.id',
                't.name',           // bu_name removed — column does not exist
                't.logo_url',
                't.is_active',
                'ts.status as subscription_status',
                'ts.trial_ends_at',
                'ts.current_period_end',
                'p.name as plan_name',
                'p.code as plan_code',
                'p.price_usd',
                DB::raw('COALESCE(bc.discount_percent, 0) as discount_percent'),
                DB::raw('bc.label as billing_label'),
                DB::raw('p.price_usd * (1 - COALESCE(bc.discount_percent, 0) / 100.0) as monthly_value')
            )
            ->whereIn('ts.status', ['active', 'trial'])
            ->orderByDesc('monthly_value')
            ->limit(7)
            ->get()
            ->map(function ($row, $i) {
                return [
                    'rank'                => $i + 1,
                    'id'                  => $row->id,
                    'name'                => $row->name,
                    'logo_url'            => $row->logo_url,
                    'plan_name'           => $row->plan_name,
                    'plan_code'           => $row->plan_code,
                    'billing_label'       => $row->billing_label,
                    'subscription_status' => $row->subscription_status,
                    'monthly_value'       => round((float) $row->monthly_value, 2),
                    'price_usd'           => (float) $row->price_usd,
                    'discount_percent'    => (float) $row->discount_percent,
                    'trial_ends_at'       => $row->trial_ends_at
                        ? Carbon::parse($row->trial_ends_at)->toDateString() : null,
                    'period_end'          => $row->current_period_end
                        ? Carbon::parse($row->current_period_end)->toDateString() : null,
                    'is_active'           => (bool) $row->is_active,
                ];
            });

        $maxVal     = $topTenants->max('monthly_value') ?: 1;
        $topTenants = $topTenants->map(fn($t) => array_merge($t, [
            'revenue_percent' => (int) round(($t['monthly_value'] / $maxVal) * 100),
        ]));

        // ── Billing & plans breakdown ─────────────────────────────────────
        // Use a LEFT JOIN to bc so discount is available without a correlated subquery
        $planBreakdown = DB::table('tenant_subscriptions as ts')
            ->join('plans as p', 'p.id', '=', 'ts.plan_id')
            ->leftJoin('plan_billing_cycles as bc', 'bc.id', '=', 'ts.billing_cycle_id')
            ->whereIn('ts.status', ['active', 'trial', 'suspended', 'cancelled'])
            ->select(
                'p.id as plan_id',
                'p.name as plan_name',
                'p.code as plan_code',
                'p.price_usd',
                'ts.status',
                DB::raw('COUNT(*) as count'),
                DB::raw("
                    SUM(
                        CASE WHEN ts.status = 'active'
                        THEN p.price_usd * (1 - COALESCE(bc.discount_percent, 0) / 100.0)
                        ELSE 0 END
                    ) as mrr_contribution
                ")
            )
            ->groupBy('p.id', 'p.name', 'p.code', 'p.price_usd', 'ts.status')
            ->orderByDesc('mrr_contribution')
            ->get();

        $plans = $planBreakdown->groupBy('plan_id')->map(function ($rows) {
            $first    = $rows->first();
            $statuses = $rows->pluck('count', 'status')->toArray();
            return [
                'plan_id'          => $first->plan_id,
                'plan_name'        => $first->plan_name,
                'plan_code'        => $first->plan_code,
                'price_usd'        => (float) $first->price_usd,
                'active'           => (int) ($statuses['active']    ?? 0),
                'trial'            => (int) ($statuses['trial']     ?? 0),
                'suspended'        => (int) ($statuses['suspended'] ?? 0),
                'cancelled'        => (int) ($statuses['cancelled'] ?? 0),
                'mrr_contribution' => round((float) $rows->sum('mrr_contribution'), 2),
            ];
        })->values();

        // Overdue & upcoming renewals
        $overdueCount = TenantSubscription::where('status', 'active')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', $now)
            ->count();

        $renewalsSoon = TenantSubscription::where('status', 'active')
            ->whereBetween('current_period_end', [$now, $now->copy()->addDays(7)])
            ->count();

        // ── Recent tenants ────────────────────────────────────────────────
        $recentTenants = Tenant::with(['activeSubscription.plan'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn($t) => [
                'id'         => $t->id,
                'name'       => $t->name,
                'logo_url'   => $t->logo_url,
                'plan_name'  => $t->activeSubscription?->plan?->name ?? 'No plan',
                'status'     => $t->activeSubscription?->status ?? 'unknown',
                'created_at' => Carbon::parse($t->created_at)->diffForHumans(),
                'is_active'  => $t->is_active,
            ]);

        // ── Response ──────────────────────────────────────────────────────
        return response()->json([
            'success' => true,
            'data'    => [
                'kpis' => [
                    [
                        'key'         => 'mrr',
                        'label'       => 'MRR',
                        'value'       => '$' . number_format($mrr, 0),
                        'raw'         => $mrr,
                        'icon'        => 'mdi-cash-multiple',
                        'color'       => 'primary',
                        'trend'       => $this->trend($mrr, $prevMrr),
                        'trend_label' => 'vs last month',
                    ],
                    [
                        'key'         => 'active_tenants',
                        'label'       => 'Active Tenants',
                        'value'       => number_format($activeTenants) . ' / ' . number_format($totalTenants),
                        'raw'         => $activeTenants,
                        'icon'        => 'mdi-domain',
                        'color'       => 'warning',
                        'trend'       => $this->trend($newThisMonth, $newLastMonth),
                        'trend_label' => '+' . $newThisMonth . ' new this month',
                    ],
                    [
                        'key'             => 'churn_rate',
                        'label'           => 'Churn Rate',
                        'value'           => $churnRate . '%',
                        'raw'             => $churnRate,
                        'icon'            => 'mdi-account-minus-outline',
                        'color'           => $churnRate > 3 ? 'error' : 'success',
                        'trend'           => $churnDelta,
                        'trend_label'     => 'vs last month',
                        'trend_inverted'  => true,
                    ],
                    [
                        'key'         => 'active_users',
                        'label'       => 'Active Users',
                        'value'       => number_format($activeUsers),
                        'raw'         => $activeUsers,
                        'icon'        => 'mdi-account-group-outline',
                        'color'       => 'secondary',
                        'trend'       => $this->trend($activeUsers, $prevActiveUsers),
                        'trend_label' => 'total platform users',
                    ],
                    [
                        'key'         => 'trial_conversion',
                        'label'       => 'Trial → Paid',
                        'value'       => $conversionRate . '%',
                        'raw'         => $conversionRate,
                        'icon'        => 'mdi-swap-horizontal',
                        'color'       => 'info',
                        'trend'       => 0,
                        'trend_label' => $convertedThisMonth . ' converted this month',
                    ],
                ],

                'top_tenants'    => $topTenants,
                'recent_tenants' => $recentTenants,

                'billing' => [
                    'plans'           => $plans,
                    'overdue_count'   => $overdueCount,
                    'renewals_soon'   => $renewalsSoon,
                    'total_active'    => TenantSubscription::where('status', 'active')->count(),
                    'total_trial'     => TenantSubscription::where('status', 'trial')->count(),
                    'total_suspended' => TenantSubscription::where('status', 'suspended')->count(),
                    'total_cancelled' => TenantSubscription::where('status', 'cancelled')->count(),
                ],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/admin/dashboard/chart?period=week
    // ─────────────────────────────────────────────────────────────────────────
    public function chart(Request $request)
    {
        $period = $request->input('period', 'month');

        [$from, $groupFormat] = match (strtolower($period)) {
            'today' => [Carbon::today(),                          'HH24:00'],
            'week'  => [Carbon::now()->subDays(6)->startOfDay(),  'YYYY-MM-DD'],
            'year'  => [Carbon::now()->subYear()->startOfMonth(), 'YYYY-MM'],
            default => [Carbon::now()->subDays(29)->startOfDay(), 'YYYY-MM-DD'],
        };

        // PostgreSQL does not allow GROUP BY alias — must repeat the full expression.
        $groupExpr = "TO_CHAR(ts.current_period_start, '{$groupFormat}')";

        $rows = DB::table('tenant_subscriptions as ts')
            ->join('plans as p', 'p.id', '=', 'ts.plan_id')
            ->leftJoin('plan_billing_cycles as bc', 'bc.id', '=', 'ts.billing_cycle_id')
            ->where('ts.current_period_start', '>=', $from)
            ->whereIn('ts.status', ['active', 'trial'])
            ->select(
                DB::raw("{$groupExpr} as label"),
                DB::raw("SUM(p.price_usd * (1 - COALESCE(bc.discount_percent, 0) / 100.0)) as mrr"),
                DB::raw('COUNT(*) as subscriptions')
            )
            ->groupByRaw($groupExpr)
            ->orderByRaw($groupExpr)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $rows->map(fn($r) => [
                'label'         => $r->label,
                'mrr'           => round((float) $r->mrr, 2),
                'subscriptions' => (int) $r->subscriptions,
            ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/v1/admin/dashboard/tenant-chart
    // ─────────────────────────────────────────────────────────────────────────
    public function tenantChart()
    {
        $data = DB::table('tenant_subscriptions as ts')
            ->join('tenants as t', 't.id', '=', 'ts.tenant_id')
            ->join('plans as p', 'p.id', '=', 'ts.plan_id')
            ->leftJoin('plan_billing_cycles as bc', 'bc.id', '=', 'ts.billing_cycle_id')
            ->where('ts.status', 'active')
            ->select(
                't.id',
                't.name',
                'p.name as plan_name',
                DB::raw('p.price_usd * (1 - COALESCE(bc.discount_percent, 0) / 100.0) as monthly_value')
            )
            ->orderByDesc('monthly_value')
            ->get()
            ->map(fn($r) => [
                'id'            => $r->id,
                'name'          => $r->name,
                'plan_name'     => $r->plan_name,
                'monthly_value' => round((float) $r->monthly_value, 2),
            ]);

        return response()->json(['success' => true, 'data' => $data]);
    }
}
