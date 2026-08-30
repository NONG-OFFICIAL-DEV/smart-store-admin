<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Traits\ResolvesBranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MartProductPerformanceController extends Controller
{
    use ResolvesBranchContext;

    /**
     * GET /api/v1/mart/reports/product-performance
     */
    public function index(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
            'branch_id' => 'nullable|uuid|exists:branches,id',
        ]);

        $tenantId = $this->resolveTenantId();
        $branchId = $this->resolveBranchId($request);
        $from     = $request->date_from . ' 00:00:00';
        $to       = $request->date_to   . ' 23:59:59';

        // ── All products with sales data ───────────────────────────────────
        $salesData = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.branch_id', $branchId)
            ->where('orders.source', 'pos')
            ->where('orders.status', 'completed')
            ->whereBetween('orders.created_at', [$from, $to])
            ->selectRaw('
                order_items.product_id,
                order_items.product_name,
                products.image_url,
                products.stock_quantity,
                products.cost_price,
                SUM(order_items.quantity)    as total_qty,
                SUM(order_items.total_price) as total_revenue,
                COUNT(DISTINCT orders.id)    as order_count
            ')
            ->groupBy(
                'order_items.product_id',
                'order_items.product_name',
                'products.image_url',
                'products.stock_quantity',
                'products.cost_price',
            )
            ->orderByDesc('total_revenue')
            ->get()
            ->map(fn($p) => [
                'product_id'    => $p->product_id,
                'product_name'  => $p->product_name,
                'image_url'     => $p->image_url,
                'stock_quantity'=> (float) $p->stock_quantity,
                'cost_price'    => (float) $p->cost_price,
                'total_qty'     => (int)   $p->total_qty,
                'total_revenue' => (float) $p->total_revenue,
                'order_count'   => (int)   $p->order_count,
                'profit'        => $p->cost_price
                    ? (float) $p->total_revenue - ((float) $p->cost_price * (int) $p->total_qty)
                    : null,
            ]);

        // ── Best sellers (top 10 by revenue) ──────────────────────────────
        $bestSellers = $salesData->take(10)->values();

        // ── Slow movers — products with stock but low/no sales ────────────
        $soldProductIds = $salesData->pluck('product_id')->toArray();

        $slowMovers = Product::where('tenant_id', $tenantId)
            ->where('is_available', true)
            ->where('stock_quantity', '>', 0)
            ->where(function ($q) use ($soldProductIds, $from, $to) {
                // Either never sold, or sold very little in period
                $q->whereNotIn('id', $soldProductIds);
            })
            ->select(['id', 'name', 'image_url', 'stock_quantity', 'reorder_level', 'cost_price'])
            ->orderByDesc('stock_quantity')
            ->limit(10)
            ->get()
            ->map(fn($p) => [
                'product_id'    => $p->id,
                'product_name'  => $p->name,
                'image_url'     => $p->image_url,
                'stock_quantity'=> (float) $p->stock_quantity,
                'total_qty'     => 0,
                'total_revenue' => 0,
                'stock_value'   => (float) $p->stock_quantity * (float) ($p->cost_price ?? 0),
            ]);

        // ── Summary ────────────────────────────────────────────────────────
        $totalRevenue  = $salesData->sum('total_revenue');
        $totalQty      = $salesData->sum('total_qty');
        $uniqueProducts= $salesData->count();

        return response()->json([
            'data' => [
                'summary' => [
                    'total_revenue'    => $totalRevenue,
                    'total_qty_sold'   => $totalQty,
                    'unique_products'  => $uniqueProducts,
                    'slow_mover_count' => $slowMovers->count(),
                ],
                'best_sellers' => $bestSellers,
                'slow_movers'  => $slowMovers,
                'all_products' => $salesData,
            ],
        ]);
    }
}

// Route::get('mart/reports/product-performance', [MartProductPerformanceController::class, 'index']);
