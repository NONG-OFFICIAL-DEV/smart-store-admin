<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Branch;
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
                    $unitName   = $product->unit ?? 'pcs';
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
                    'receipt'        => $this->buildReceipt($order, $branch, $customerType),
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
            ->where(function ($q) {
                $q->where('product_type', 'retail')
                    ->orWhere('track_stock', true);
            })
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
            ->paginate(40);

        return response()->json(['success' => true, 'data' => $products]);
    }

    private function buildReceipt(Order $order, Branch $branch, string $customerType): array
    {
        return [
            'order_number'   => $order->order_number,
            'branch_name'    => $branch->name,
            'cashier'        => auth()->user()?->email,
            'customer_type'  => $customerType,
            'items'          => $order->items->map(fn($i) => [
                'name'        => $i->product_name,
                'unit'        => $i->unit_name,
                'qty'         => $i->quantity,
                'unit_price'  => $i->unit_price,
                'total_price' => $i->total_price,
            ]),
            'subtotal'       => $order->subtotal,
            'discount'       => $order->discount_amount,
            'tax'            => $order->tax_amount,
            'total'          => $order->total_amount,
            'payment_method' => $order->payment_method,
            'printed_at'     => now()->toDateTimeString(),
        ];
    }
}
