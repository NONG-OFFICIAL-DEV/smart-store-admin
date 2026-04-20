<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Payment;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MartPosController extends Controller
{
    // for create order in mart POS
    // ── POST /api/v1/mart/pos/orders ──────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'branch_id'               => 'required|uuid|exists:branches,id',
            'payment_method'          => 'required|in:cash,card,qr,transfer',
            'customer_type'           => 'nullable|in:retail,wholesale',
            'cash_tendered'           => 'nullable|numeric|min:0',
            'notes'                   => 'nullable|string|max:500',
            'discount_amount'         => 'nullable|numeric|min:0',
            'items'                   => 'required|array|min:1',
            'items.*.product_id'      => 'required|uuid|exists:products,id',
            'items.*.product_unit_id' => 'nullable|uuid|exists:product_units,id',
            'items.*.quantity'        => 'required|integer|min:1',
        ]);

        $branch       = Branch::with('tenant')->findOrFail($request->branch_id);
        $cashier      = auth()->user();
        $staff        = $cashier->staff;
        $customerType = $request->customer_type ?? 'retail';

        // Map payment method to payments table enum
        $paymentMethodMap = [
            'cash'     => 'cash',
            'card'     => 'card',
            'qr'       => 'qr_code',
            'transfer' => 'online',
        ];

        return DB::transaction(function () use (
            $request,
            $branch,
            $cashier,
            $staff,
            $customerType,
            $paymentMethodMap
        ) {
            $subtotal    = 0;
            $itemsData   = [];
            $stockErrors = [];

            foreach ($request->items as $item) {
                $product     = Product::findOrFail($item['product_id']);
                $productUnit = isset($item['product_unit_id'])
                    ? ProductUnit::findOrFail($item['product_unit_id'])
                    : null;

                // ── Resolve price ──────────────────────────────────────────────
                if ($productUnit) {
                    $unitPrice  = $productUnit->priceFor($customerType);
                    $qtyPerBase = (float) $productUnit->qty_per_base;
                    $unitName   = $productUnit->unit_label ?? $productUnit->unit_name;
                } else {
                    $unitPrice  = (float) ($product->selling_price ?? $product->base_price ?? 0);
                    $qtyPerBase = 1;
                    $unitName   = $product->unit_name ?? 'pcs';
                }

                // ── Stock check ────────────────────────────────────────────────
                $baseQtyNeeded = $item['quantity'] * $qtyPerBase;
                if ((float) $product->stock_quantity < $baseQtyNeeded) {
                    $available     = floor($product->stock_quantity / $qtyPerBase);
                    $stockErrors[] = "'{$product->name}' ({$unitName}): only {$available} available";
                    continue;
                }

                $totalPrice = $unitPrice * $item['quantity'];
                $subtotal  += $totalPrice;

                $itemsData[] = [
                    'product_id'      => $product->id,
                    'product_unit_id' => $productUnit?->id,
                    'product_name'    => $product->name,
                    'unit_name'       => $unitName,
                    'qty_per_base'    => $qtyPerBase,
                    'quantity'        => $item['quantity'],
                    'unit_price'      => $unitPrice,
                    'discount_amount' => 0,
                    'total_price'     => $totalPrice,
                    'status'          => 'served',
                    '_product'        => $product,
                    '_base_qty'       => $baseQtyNeeded,
                ];
            }

            if (!empty($stockErrors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock',
                    'errors'  => $stockErrors,
                ], 422);
            }

            // ── Totals ─────────────────────────────────────────────────────────
            $discountAmount = (float) ($request->discount_amount ?? 0);
            $taxRate        = (float) ($branch->tenant->tax_rate ?? 0);
            $taxAmount      = round(($subtotal - $discountAmount) * $taxRate, 2);
            $totalAmount    = $subtotal - $discountAmount + $taxAmount;

            // ── Create order ───────────────────────────────────────────────────
            $order = Order::create([
                'branch_id'             => $branch->id,
                'cashier_id'            => $staff?->id,
                'order_type'            => 'takeaway',
                'status'                => 'completed',
                'source'                => 'pos',
                'subtotal'              => $subtotal,
                'tax_amount'            => $taxAmount,
                'service_charge_amount' => 0,
                'discount_amount'       => $discountAmount,
                'total_amount'          => $totalAmount,
                'notes'                 => $request->notes,
                'completed_at'          => now(),
            ]);

            // ── Create items + deduct stock + log movement ─────────────────────
            foreach ($itemsData as $itemData) {
                $product = $itemData['_product'];
                $baseQty = $itemData['_base_qty'];
                unset($itemData['_product'], $itemData['_base_qty']);

                OrderItem::create(['order_id' => $order->id, ...$itemData]);

                $qtyBefore = (float) $product->stock_quantity;
                $qtyAfter  = $qtyBefore - $baseQty;
                $product->decrement('stock_quantity', $baseQty);

                StockMovement::create([
                    'branch_id'      => $branch->id,
                    'product_id'     => $product->id,
                    'movement_type'  => 'sale',
                    'quantity'       => -$baseQty,
                    'qty_before'     => $qtyBefore,
                    'qty_after'      => $qtyAfter,
                    'reference_type' => 'order',
                    'reference_id'   => $order->id,
                    'notes'          => "POS sale · {$customerType}",
                ]);
            }

            // ── Create payment record ──────────────────────────────────────────
            $cashTendered = (float) ($request->cash_tendered ?? $totalAmount);
            $changeGiven  = $request->payment_method === 'cash'
                ? max(0, $cashTendered - $totalAmount)
                : null;

            $payment = Payment::create([
                'order_id'       => $order->id,
                'branch_id'      => $branch->id,
                'staff_id'       => $staff?->id,
                'payment_method' => $paymentMethodMap[$request->payment_method] ?? 'cash',
                'amount'         => $totalAmount,
                'change_given'   => $changeGiven,
                'currency'       => 'USD',
                'status'         => 'completed',
                'paid_at'        => now(),
            ]);

            // ── Order status history ───────────────────────────────────────────
            OrderStatusHistory::create([
                'order_id'    => $order->id,
                'from_status' => null,
                'to_status'   => 'completed',
                'notes'       => "POS sale · {$cashier->email} · {$customerType}",
            ]);

            $order->load('items');

            return response()->json([
                'success' => true,
                'data'    => [
                    'id'             => $order->id,
                    'order_number'   => $order->order_number,
                    'customer_type'  => $customerType,
                    'subtotal'       => $order->subtotal,
                    'discount'       => $order->discount_amount,
                    'tax'            => $order->tax_amount,
                    'total_amount'   => $order->total_amount,
                    'payment_method' => $request->payment_method,
                    'cash_tendered'  => $cashTendered,
                    'change_given'   => $changeGiven,
                    'payment_id'     => $payment->id,
                    'items'          => $order->items,
                    'receipt'        => $this->buildReceipt($order, $payment, $branch, $customerType),
                ],
            ], 201);
        });
    }

    public function index(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|uuid|exists:branches,id',
        ]);

        $orders = Order::with('items')
            ->where('branch_id', $request->branch_id)
            ->where('source', 'pos')
            ->whereDate('created_at', today())
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $orders]);
    }

    // ── GET /api/v1/mart/pos/products ─────────────────────────────────────────
    // Products with their units — for POS product grid
    public function products(Request $request)
    {
        $request->validate([
            'branch_id'   => 'required|uuid|exists:branches,id',
            'category_id' => 'nullable|uuid',
            'search'      => 'nullable|string',
        ]);

        $branch = Branch::findOrFail($request->branch_id);

        $products = Product::with(['activeUnits', 'category:id,name'])

            ->where('tenant_id', $branch->tenant_id)
            ->where('is_available', true)
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when(
                $request->search,
                fn($q) =>
                $q->where('name', 'ilike', "%{$request->search}%")
                    ->orWhere('barcode', $request->search)
                    ->orWhereHas(
                        'activeUnits',
                        fn($u) =>
                        $u->where('barcode', $request->search)
                    )
            )
            ->orderBy('category_id')
            ->paginate(10000);

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function categories(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|uuid|exists:branches,id',
        ]);

        $branch = Branch::findOrFail($request->branch_id);

        $categories = Category::whereHas('products', function ($q) use ($branch) {
            $q->where('tenant_id', $branch->tenant_id)
                ->where('is_available', true);
        })
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'icon', 'color', 'image_url', 'sort_order']);

        return response()->json(['success' => true, 'data' => $categories]);
    }

    private function buildReceipt(Order $order, Payment $payment, Branch $branch, string $customerType): array
    {
        return [
            'order_number'   => $order->order_number,
            'branch_name'    => $branch->name,
            'branch_address' => $branch->address ?? null,
            'branch_phone'   => $branch->phone   ?? null,
            'cashier'        => auth()->user()?->email,
            'customer_type'  => $customerType,
            'date'           => now()->toDateTimeString(),
            'items'          => $order->items->map(fn($i) => [
                'name'        => $i->product_name,
                'unit'        => $i->unit_name,   // ← snapshot from order_items
                'qty'         => $i->quantity,
                'unit_price'  => (float) $i->unit_price,
                'total_price' => (float) $i->total_price,
            ]),
            'subtotal'        => (float) $order->subtotal,
            'discount'        => (float) $order->discount_amount,
            'tax'             => (float) $order->tax_amount,
            'total'           => (float) $order->total_amount,
            'payment_method'  => $payment->payment_method,
            'cash_tendered'   => (float) ($payment->amount + ($payment->change_given ?? 0)),
            'change_given'    => (float) ($payment->change_given ?? 0),
            'printed_at'      => now()->format('d/m/Y H:i'),
        ];
    }
    /**
     * GET /api/v1/mart/reports/inventory
     */
    public function reportStock(Request $request)
    {
        $request->validate([
            'branch_id'   => 'nullable|uuid|exists:branches,id',
            'date_from'   => 'nullable|date',
            'date_to'     => 'nullable|date|after_or_equal:date_from',
            'category_id' => 'nullable|uuid',
            'search'      => 'nullable|string',
        ]);

        // $tenantId = auth()->user()->staff->tenant_id;
        $branchId = $request->branch_id ?? auth()->user()->staff->branch_id;
        $from     = $request->date_from ? $request->date_from . ' 00:00:00' : null;
        $to       = $request->date_to   ? $request->date_to   . ' 23:59:59' : null;

        // ── Current stock snapshot ─────────────────────────────────────────
        $products = Product::with(['category:id,name', 'activeUnits'])
            // ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('product_type', 'retail')
                    ->orWhere('track_stock', true);
            })
            // ->where('is_active', true)
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->search, fn($q) => $q->where('name', 'ilike', "%{$request->search}%"))
            ->orderBy('name')
            ->get()
            ->map(fn($p) => [
                'id'              => $p->id,
                'name'            => $p->name,
                'sku'             => $p->sku,
                'image_url'       => $p->image_url,
                'category'        => $p->category?->name,
                'unit'            => $p->unit,
                'stock_quantity'  => (float) $p->stock_quantity,
                'reorder_level'   => $p->reorder_level ? (float) $p->reorder_level : null,
                'cost_price'      => (float) $p->cost_price,
                'retail_price'    => (float) $p->retail_price,
                'stock_value'     => (float) $p->stock_quantity * (float) ($p->cost_price ?? 0),
                'retail_value'    => (float) $p->stock_quantity * (float) ($p->retail_price ?? 0),
                'stock_status'    => $this->stockStatus($p),
                'active_units'    => $p->activeUnits->map(fn($u) => [
                    'id'           => $u->id,
                    'unit_label'   => $u->unit_label ?? $u->unit_name,
                    'qty_per_base' => (float) $u->qty_per_base,
                    'is_base_unit' => (bool) $u->is_base_unit,
                ]),
            ]);

        // ── Summary ────────────────────────────────────────────────────────
        $totalProducts  = $products->count();
        $inStock        = $products->where('stock_status', 'in_stock')->count();
        $lowStock       = $products->where('stock_status', 'low_stock')->count();
        $outOfStock     = $products->where('stock_status', 'out_of_stock')->count();
        $totalCostValue = $products->sum('stock_value');
        $totalRetailValue = $products->sum('retail_value');
        $potentialProfit  = $totalRetailValue - $totalCostValue;

        // ── Stock movement summary (if date range provided) ────────────────
        $movementSummary = null;
        if ($from && $to) {
            $movementSummary = StockMovement::where('branch_id', $branchId)
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw("
                    movement_type,
                    COUNT(*)            as count,
                    SUM(ABS(quantity))  as total_qty,
                    SUM(
                        CASE WHEN unit_cost IS NOT NULL
                        THEN ABS(quantity) * unit_cost
                        ELSE 0 END
                    ) as total_value
                ")
                ->groupBy('movement_type')
                ->get();
        }

        // ── Stock movement by product (if date range) ──────────────────────
        $productMovements = null;
        if ($from && $to) {
            $productMovements = StockMovement::where('stock_movements.branch_id', $branchId)
                ->whereBetween('stock_movements.created_at', [$from, $to])
                ->join('products', 'stock_movements.product_id', '=', 'products.id')
                ->selectRaw("
        stock_movements.product_id,
        products.name        as product_name,
        products.image_url,
        SUM(CASE WHEN stock_movements.quantity > 0 THEN stock_movements.quantity ELSE 0 END)          as total_in,
        SUM(CASE WHEN stock_movements.quantity < 0 THEN ABS(stock_movements.quantity) ELSE 0 END)     as total_out
    ")
                ->groupBy('stock_movements.product_id', 'products.name', 'products.image_url')
                ->orderByDesc('total_out')
                ->limit(20)
                ->get()
                ->map(function ($row) {
                    $row->image_url = $row->image_url
                        ? asset('storage/' . $row->image_url)
                        : null;
                    return $row;
                });
        }

        // ── Category breakdown ─────────────────────────────────────────────
        $byCategory = $products->groupBy('category')->map(fn($items, $cat) => [
            'category'    => $cat ?? 'Uncategorized',
            'count'       => $items->count(),
            'stock_value' => $items->sum('stock_value'),
            'out_of_stock' => $items->where('stock_status', 'out_of_stock')->count(),
            'low_stock'   => $items->where('stock_status', 'low_stock')->count(),
        ])->values();

        return response()->json([
            'data' => [
                'summary' => [
                    'total_products'    => $totalProducts,
                    'in_stock'          => $inStock,
                    'low_stock'         => $lowStock,
                    'out_of_stock'      => $outOfStock,
                    'total_cost_value'  => round($totalCostValue, 2),
                    'total_retail_value' => round($totalRetailValue, 2),
                    'potential_profit'  => round($potentialProfit, 2),
                ],
                'products'          => $products,
                'by_category'       => $byCategory,
                'movement_summary'  => $movementSummary,
                'product_movements' => $productMovements,
            ],
        ]);
    }

    private function stockStatus($product): string
    {
        if ((float) $product->stock_quantity <= 0) return 'out_of_stock';
        if ($product->reorder_level && (float) $product->stock_quantity <= (float) $product->reorder_level)
            return 'low_stock';
        return 'in_stock';
    }
}
