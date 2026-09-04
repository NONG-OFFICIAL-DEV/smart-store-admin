<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MartPurchaseOrder;
use App\Models\MartPurchaseOrderItem;
use App\Traits\ResolvesBranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MartPurchaseReportController extends Controller
{
    use ResolvesBranchContext;

    /**
     * GET /api/v1/mart/reports/purchases
     */
    public function index(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
            'branch_id' => 'nullable|uuid|exists:branches,id',
        ]);

        $branchId = $this->resolveBranchId($request);
        $from     = $request->date_from . ' 00:00:00';
        $to       = $request->date_to   . ' 23:59:59';

        $base = MartPurchaseOrder::where('mart_purchase_orders.branch_id', $branchId)
            ->whereBetween('mart_purchase_orders.created_at', [$from, $to]);

        // ── Summary ────────────────────────────────────────────────────────
        $summary = $base->clone()->selectRaw('
            COUNT(*)                                            as total_pos,
            SUM(total_amount)                                   as total_spent,
            AVG(total_amount)                                   as avg_po_value,
            SUM(CASE WHEN status = \'received\' THEN 1 ELSE 0 END)          as received_count,
            SUM(CASE WHEN status = \'partially_received\' THEN 1 ELSE 0 END) as partial_count,
            SUM(CASE WHEN status = \'pending\' OR status = \'draft\' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = \'cancelled\' THEN 1 ELSE 0 END)          as cancelled_count
        ')->first();

        // ── Spent by supplier ─────────────────────────────────────────────
        $bySupplier = $base->clone()
            ->join('suppliers', 'mart_purchase_orders.supplier_id', '=', 'suppliers.id')
            ->selectRaw('
                suppliers.id   as supplier_id,
                suppliers.name as supplier_name,
                COUNT(mart_purchase_orders.id) as po_count,
                SUM(mart_purchase_orders.total_amount) as total_spent
            ')
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('total_spent')
            ->get();

        // ── Spent over time (by day) ───────────────────────────────────────
        $chart = $base->clone()
            ->selectRaw("
                to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD') as label,
                COUNT(*) as po_count,
                SUM(total_amount) as total_spent
            ")
            ->groupByRaw("to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD')")
            ->orderByRaw("to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD')")
            ->get();

        // ── Top purchased products ─────────────────────────────────────────
        $topProducts = MartPurchaseOrderItem::join(
            'mart_purchase_orders',
            'mart_purchase_order_items.mart_purchase_order_id',
            '=',
            'mart_purchase_orders.id'
        )
            ->where('mart_purchase_orders.branch_id', $branchId)
            ->whereBetween('mart_purchase_orders.created_at', [$from, $to])
            ->selectRaw('
                mart_purchase_order_items.product_id,
                mart_purchase_order_items.product_name,
                SUM(mart_purchase_order_items.quantity_ordered)  as total_ordered,
                SUM(mart_purchase_order_items.quantity_received) as total_received,
                SUM(mart_purchase_order_items.total_cost)        as total_cost
            ')
            ->groupBy(
                'mart_purchase_order_items.product_id',
                'mart_purchase_order_items.product_name'
            )
            ->orderByDesc('total_cost')
            ->limit(10)
            ->get();

        // ── Recent POs ────────────────────────────────────────────────────
        $recentPos = $base->clone()
            ->with(['supplier', 'items'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn($po) => [
                'id'           => $po->id,
                'po_number'    => $po->po_number,
                'supplier'     => $po->supplier?->name,
                'items_count'  => $po->items->count(),
                'total_amount' => (float) $po->total_amount,
                'status'       => $po->status,
                'created_at'   => $po->created_at,
            ]);

        return response()->json([
            'data' => [
                'summary'      => [
                    'total_pos'       => (int)   $summary->total_pos,
                    'total_spent'     => (float) $summary->total_spent     ?? 0,
                    'avg_po_value'    => (float) $summary->avg_po_value    ?? 0,
                    'received_count'  => (int)   $summary->received_count,
                    'partial_count'   => (int)   $summary->partial_count,
                    'pending_count'   => (int)   $summary->pending_count,
                    'cancelled_count' => (int)   $summary->cancelled_count,
                ],
                'by_supplier'  => $bySupplier,
                'chart'        => $chart,
                'top_products' => $topProducts,
                'recent_pos'   => $recentPos,
            ],
        ]);
    }
}
