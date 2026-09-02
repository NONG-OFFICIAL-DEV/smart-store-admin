<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Payment;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use App\Traits\ResolvesBranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MartPosController extends Controller
{
    use ResolvesBranchContext;

    // ── POST /api/v1/mart/pos/orders ──────────────────────────────────────────
    public function storeOrders(Request $request)
    {
        $request->validate([
            'branch_id'                   => 'required|uuid|exists:branches,id',
            'payment_method'              => 'required|in:cash,card,qr,transfer',
            'customer_type'               => 'nullable|in:retail,wholesale,mixed',
            'cash_tendered'               => 'nullable|numeric|min:0',
            'notes'                       => 'nullable|string|max:500',
            'discount_amount'             => 'nullable|numeric|min:0',
            'customer_id'                 => 'nullable|uuid',
            'items'                       => 'required|array|min:1',
            'items.*.product_id'          => 'required|uuid|exists:products,id',
            'items.*.product_unit_id'     => 'nullable|uuid|exists:product_units,id',
            'items.*.quantity'            => 'required|integer|min:1',
            'items.*.customer_type'       => 'nullable|in:retail,wholesale,lid_exchange',
            'items.*.topup_amount'        => 'nullable|numeric|min:0',
        ]);

        $branch       = Branch::with('tenant')->findOrFail($request->branch_id);
        $cashier      = auth()->user();
        $staff        = $cashier->staff;
        $customerType = $request->customer_type ?? 'retail';

        // Resolved through the model layer (not a raw `exists:` rule) so a
        // cross-tenant id gets a clean 422 instead of silently bypassing
        // TenantScope — optional, so absence is fine (walk-in sale).
        $customer = $request->filled('customer_id') ? Customer::find($request->customer_id) : null;
        if ($request->filled('customer_id') && ! $customer) {
            abort(response()->json(['success' => false, 'message' => 'Customer not found.'], 422));
        }

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
            $paymentMethodMap,
            $customer
        ) {
            $subtotal    = 0;
            $itemsData   = [];
            $stockErrors = [];

            foreach ($request->items as $item) {
                // Lock the product row for the duration of this transaction so a
                // concurrent sale of the same product can't read stale stock and
                // oversell (check-then-decrement race).
                $product     = Product::lockForUpdate()->findOrFail($item['product_id']);
                $productUnit = isset($item['product_unit_id'])
                    ? ProductUnit::findOrFail($item['product_unit_id'])
                    : null;

                // ── Per-item customer type ─────────────────────────────────
                $itemCustomerType = $item['customer_type'] ?? $customerType;
                $isLidExchange    = $itemCustomerType === 'lid_exchange';

                // ── Resolve price ──────────────────────────────────────────
                if ($isLidExchange) {
                    $unitPrice  = (float) ($item['topup_amount'] ?? 0);
                    $qtyPerBase = $productUnit ? (float) $productUnit->qty_per_base : 1;
                    $unitName   = $productUnit?->unit_label
                        ?? $productUnit?->unit_name
                        ?? $product->unit
                        ?? 'pcs';
                } elseif ($productUnit) {
                    $unitPrice  = $productUnit->priceFor($itemCustomerType);
                    $qtyPerBase = (float) $productUnit->qty_per_base;
                    $unitName   = $productUnit->unit_label ?? $productUnit->unit_name;
                } else {
                    $unitPrice  = (float) ($product->selling_price ?? $product->base_price ?? 0);
                    $qtyPerBase = 1;
                    $unitName   = $product->unit ?? 'pcs';
                }

                // ── Stock check ────────────────────────────────────────────
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
                    'product_name'    => $product->name,
                    'unit_name'       => $unitName,
                    'qty_per_base'    => $qtyPerBase,
                    'quantity'        => $item['quantity'],
                    'unit_price'      => $unitPrice,
                    'discount_amount' => 0,
                    'total_price'     => $totalPrice,
                    'status'          => 'served',
                    'customer_type'   => $itemCustomerType,
                    'is_lid_exchange' => $isLidExchange,
                    'topup_amount'    => $isLidExchange ? $unitPrice : null,
                    '_product'        => $product,
                    '_base_qty'       => $baseQtyNeeded,
                    '_customer_type'  => $itemCustomerType,
                ];
            }

            if (!empty($stockErrors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock',
                    'errors'  => $stockErrors,
                ], 422);
            }

            // ── Detect order-level customer type ───────────────────────────
            $orderCustomerTypes = array_unique(array_column($itemsData, '_customer_type'));
            $orderCustomerType  = count($orderCustomerTypes) > 1
                ? 'mixed'
                : ($orderCustomerTypes[0] ?? $customerType);

            // ── Totals ─────────────────────────────────────────────────────
            // Clamp discount to the subtotal so it can never drive the order negative.
            $discountAmount = min((float) ($request->discount_amount ?? 0), $subtotal);
            $taxRate        = (float) ($branch->tenant->tax_rate ?? 0);
            $taxAmount      = round(($subtotal - $discountAmount) * $taxRate, 2);
            $totalAmount    = $subtotal - $discountAmount + $taxAmount;

            // ── Reject short cash payments instead of silently completing the order ──
            if (
                $request->payment_method === 'cash'
                && $request->filled('cash_tendered')
                && (float) $request->cash_tendered < $totalAmount
            ) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Cash tendered is less than the order total.',
                ], 422));
            }

            // ── Create order ───────────────────────────────────────────────
            $order = Order::create([
                'branch_id'             => $branch->id,
                'cashier_id'            => $staff?->id,
                'order_type'            => 'takeaway',
                'customer_id'           => $customer?->id,
                'status'                => 'completed',
                'source'                => 'pos',
                'customer_type'         => $orderCustomerType,
                'subtotal'              => $subtotal,
                'tax_amount'            => $taxAmount,
                'service_charge_amount' => 0,
                'discount_amount'       => $discountAmount,
                'total_amount'          => $totalAmount,
                'notes'                 => $request->notes,
                'completed_at'          => now(),
            ]);

            // ── Create items + deduct stock + log movement ─────────────────
            foreach ($itemsData as $itemData) {
                $product          = $itemData['_product'];
                $baseQty          = $itemData['_base_qty'];
                $itemCustomerType = $itemData['_customer_type'];

                unset($itemData['_product'], $itemData['_base_qty'], $itemData['_customer_type']);

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
                    'notes'          => "POS sale · {$itemCustomerType}",
                ]);
            }

            // ── Create payment record ──────────────────────────────────────
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
                'currency' => $branch->tenant->currency ?? 'USD',
                'status'         => 'completed',
                'paid_at'        => now(),
            ]);

            // ── Order status history ───────────────────────────────────────
            OrderStatusHistory::create([
                'order_id'    => $order->id,
                'from_status' => null,
                'to_status'   => 'completed',
                'notes'       => "POS sale · {$cashier->email} · {$orderCustomerType}",
            ]);

            $order->load('items');

            return response()->json([
                'success' => true,
                'data'    => [
                    'id'             => $order->id,
                    'order_number'   => $order->order_number,
                    'customer_type'  => $orderCustomerType,
                    'subtotal'       => $order->subtotal,
                    'discount'       => $order->discount_amount,
                    'tax'            => $order->tax_amount,
                    'total_amount'   => $order->total_amount,
                    'payment_method' => $request->payment_method,
                    'cash_tendered'  => $cashTendered,
                    'change_given'   => $changeGiven,
                    'payment_id'     => $payment->id,
                    'customer_name'  => $customer ? trim("{$customer->first_name} {$customer->last_name}") : null,
                    'items'          => $order->items,
                    'receipt'        => $this->buildReceipt($order, $payment, $branch, $orderCustomerType, $customer),
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

        $products = Product::with(['activeUnits', 'category:id,name,is_lid_exchange'])

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
            ->orderBy('sort_order')
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

    private function buildReceipt(Order $order, Payment $payment, Branch $branch, string $customerType, ?Customer $customer = null): array
    {
        return [
            'order_number'   => $order->order_number,
            'branch_name'    => $branch->name,
            'branch_address' => $branch->address ?? null,
            'branch_phone'   => $branch->phone   ?? null,
            'cashier'        => auth()->user()?->email,
            'customer_type'  => $customerType,
            'customer_name'  => $customer ? trim("{$customer->first_name} {$customer->last_name}") : null,
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
     *
     * Fixed version:
     * - Loads retail_price / cost_price from the base product_unit (is_base_unit = true)
     *   because the `products` table itself does not carry those columns.
     * - stock_quantity, reorder_level, unit are assumed to be on `products`
     *   (add them if they're stored elsewhere).
     * - Removed columns that don't exist in the schema (track_stock is kept as a
     *   guard, adjust to your real column name if different).
     * - category_id filter is forwarded to the query.
     * - product_movements image_url uses asset() correctly.
     */

    public function reportStock(Request $request)
    {
        $request->validate([
            'branch_id'   => 'nullable|uuid|exists:branches,id',
            'date_from'   => 'nullable|date',
            'date_to'     => 'nullable|date|after_or_equal:date_from',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'search'      => 'nullable|string|max:100',
        ]);

        $branchId = $this->resolveBranchId($request);
        $from     = $request->date_from ? $request->date_from . ' 00:00:00' : null;
        $to       = $request->date_to   ? $request->date_to   . ' 23:59:59' : null;

        // ── Current stock snapshot ─────────────────────────────────────────────
        // Eager-load the base unit so we can pull retail_price / cost_price / unit label.
        $products = Product::with([
            'category:id,name',
            'activeUnits' => fn($q) => $q->orderBy('sort_order'),
        ])

            ->when($request->category_id, fn($q, $v) => $q->where('category_id', $v))
            ->when($request->search,      fn($q, $v) => $q->where('name', 'ilike', "%{$v}%"))
            ->orderBy('name')
            ->get()
            ->map(function ($p) {
                // Resolve pricing from the base unit; fall back to product-level cost_price.
                $baseUnit   = $p->activeUnits->firstWhere('is_base_unit', true)
                    ?? $p->activeUnits->first();
                $costPrice   = (float) ($baseUnit?->cost_price   ?? $p->cost_price   ?? 0);
                $retailPrice = (float) ($baseUnit?->retail_price ?? 0);
                $stockQty    = (float) ($p->stock_quantity ?? 0);

                return [
                    'id'             => $p->id,
                    'name'           => $p->name,
                    'sku'            => $p->sku,
                    'image_url'      => $p->image_url,
                    'category'       => $p->category?->name,
                    'unit'           => $baseUnit?->unit_label ?? $baseUnit?->unit_name ?? 'pcs',
                    'stock_quantity' => $stockQty,
                    'reorder_level'  => $p->reorder_level ? (float) $p->reorder_level : null,
                    'cost_price'     => $costPrice,
                    'retail_price'   => $retailPrice,
                    'stock_value'    => round($stockQty * $costPrice,   2),
                    'retail_value'   => round($stockQty * $retailPrice, 2),
                    'stock_status'   => $this->stockStatus($p),
                    'active_units'   => $p->activeUnits->map(fn($u) => [
                        'id'           => $u->id,
                        'unit_label'   => $u->unit_label ?? $u->unit_name,
                        'qty_per_base' => (float) $u->qty_per_base,
                        'retail_price' => (float) $u->retail_price,
                        'cost_price'   => (float) ($u->cost_price ?? 0),
                        'is_base_unit' => (bool)  $u->is_base_unit,
                    ]),
                ];
            });

        // ── Summary ────────────────────────────────────────────────────────────
        $totalProducts    = $products->count();
        $inStock          = $products->where('stock_status', 'in_stock')->count();
        $lowStock         = $products->where('stock_status', 'low_stock')->count();
        $outOfStock       = $products->where('stock_status', 'out_of_stock')->count();
        $totalCostValue   = $products->sum('stock_value');
        $totalRetailValue = $products->sum('retail_value');
        $potentialProfit  = $totalRetailValue - $totalCostValue;

        // ── Stock movement summary (only when date range given) ────────────────
        $movementSummary = null;
        if ($from && $to) {
            $movementSummary = StockMovement::where('branch_id', $branchId)
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw("
                movement_type,
                COUNT(*)           AS count,
                SUM(ABS(quantity)) AS total_qty,
                SUM(
                    CASE WHEN unit_cost IS NOT NULL
                    THEN ABS(quantity) * unit_cost
                    ELSE 0 END
                ) AS total_value
            ")
                ->groupBy('movement_type')
                ->get();
        }

        // ── Top stock movers by product (only when date range given) ──────────
        $productMovements = null;
        if ($from && $to) {
            $productMovements = StockMovement::where('stock_movements.branch_id', $branchId)
                ->whereBetween('stock_movements.created_at', [$from, $to])
                ->join('products', 'stock_movements.product_id', '=', 'products.id')
                ->selectRaw("
                stock_movements.product_id,
                products.name      AS product_name,
                products.image_url,
                SUM(CASE WHEN stock_movements.quantity > 0
                    THEN  stock_movements.quantity ELSE 0 END)          AS total_in,
                SUM(CASE WHEN stock_movements.quantity < 0
                    THEN ABS(stock_movements.quantity) ELSE 0 END)      AS total_out
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

        // ── Category breakdown ─────────────────────────────────────────────────
        $byCategory = $products
            ->groupBy('category')
            ->map(fn($items, $cat) => [
                'category'    => $cat ?: 'Uncategorized',
                'count'       => $items->count(),
                'stock_value' => round($items->sum('stock_value'), 2),
                'out_of_stock' => $items->where('stock_status', 'out_of_stock')->count(),
                'low_stock'   => $items->where('stock_status', 'low_stock')->count(),
            ])
            ->values();

        return response()->json([
            'data' => [
                'summary' => [
                    'total_products'     => $totalProducts,
                    'in_stock'           => $inStock,
                    'low_stock'          => $lowStock,
                    'out_of_stock'       => $outOfStock,
                    'total_cost_value'   => round($totalCostValue,   2),
                    'total_retail_value' => round($totalRetailValue, 2),
                    'potential_profit'   => round($potentialProfit,  2),
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
        $qty = (float) ($product->stock_quantity ?? 0);
        if ($qty <= 0) return 'out_of_stock';
        if ($product->reorder_level && $qty <= (float) $product->reorder_level) return 'low_stock';
        return 'in_stock';
    }
}
