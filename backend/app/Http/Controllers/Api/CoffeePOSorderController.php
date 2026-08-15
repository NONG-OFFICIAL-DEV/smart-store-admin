<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoffeePOSorderController extends Controller
{
    // ── POST /api/v1/coffee/pos/orders ────────────────────────────────────
    public function coffeeOrders(Request $request)
    {
        $request->validate([
            'branch_id'              => 'required|uuid|exists:branches,id',
            'payment_method'         => 'required|in:cash,card,qr,transfer',
            'cash_tendered'          => 'nullable|numeric|min:0',
            'change_given'           => 'nullable|numeric|min:0',
            'notes'                  => 'nullable|string|max:500',
            'discount_amount'        => 'nullable|numeric|min:0',
            'items'                  => 'required|array|min:1',
            'items.*.product_id'     => 'required|uuid|exists:products,id',
            'items.*.variant_id'     => 'nullable|uuid|exists:product_variants,id',
            'items.*.quantity'       => 'required|integer|min:1',
            'items.*.price'          => 'nullable|numeric|min:0',
            'items.*.note'           => 'nullable|string|max:200',
            'items.*.customizations' => 'nullable',   // accept any shape — object or array
        ]);

        $branch  = Branch::with('tenant')->findOrFail($request->branch_id);
        $cashier = auth()->user();
        $staff   = $cashier->staff;

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
            $paymentMethodMap
        ) {
            $subtotal  = 0;
            $itemsData = [];

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);

                // ── Resolve variant ────────────────────────────────────────────────
                $variant  = isset($item['variant_id'])
                    ? ProductVariant::find($item['variant_id'])
                    : null;

                // Base price + variant adjustment — always derived server-side.
                // Client-submitted `items.*.price` is never trusted for billing
                // (it would let any POS caller sell an item at an arbitrary price).
                $unitPrice = (float) $product->base_price
                    + (float) ($variant?->price_adjustment ?? 0);

                // ── Parse customizations — display only, never hits DB ────────────
                $customizationsSnapshot = $this->parseCustomizations(
                    $item['customizations'] ?? null
                );

                // Inject variant into customizations snapshot for print ticket
                // so barista sees "Size: M" on the order ticket
                if ($variant) {
                    array_unshift($customizationsSnapshot, [
                        'label' => 'Size',
                        'value' => $variant->name,
                    ]);
                }

                $totalPrice = $unitPrice * $item['quantity'];
                $subtotal  += $totalPrice;

                $itemsData[] = [
                    'product_id'      => $product->id,
                    'variant_id'      => $variant?->id,       // snapshot FK
                    'product_name'    => $product->name,
                    'variant_name'    => $variant?->name,      // snapshot label
                    'unit_name'       => $product->unit ?? 'cup',
                    'quantity'        => $item['quantity'],
                    'unit_price'      => $unitPrice,
                    'discount_amount' => 0,
                    'total_price'     => $totalPrice,
                    'notes'           => $item['note'] ?? null,
                    'status'          => 'served',
                    '_customizations' => $customizationsSnapshot,
                ];
            }

            // ── Totals ─────────────────────────────────────────────────────
            // Clamp discount to the subtotal so it can never drive the order negative.
            $discountAmount      = min((float) ($request->discount_amount ?? 0), $subtotal);
            $taxRate             = (float) ($branch->tax_rate ?? $branch->tenant->tax_rate ?? 0);
            $serviceChargeRate   = (float) ($branch->service_charge_rate ?? 0);
            $taxAmount           = round(($subtotal - $discountAmount) * $taxRate, 2);
            $serviceChargeAmount = round($subtotal * $serviceChargeRate, 2);
            $totalAmount         = $subtotal - $discountAmount + $taxAmount + $serviceChargeAmount;

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
                'status'                => 'completed',
                'source'                => 'pos',
                'subtotal'              => $subtotal,
                'tax_amount'            => $taxAmount,
                'service_charge_amount' => $serviceChargeAmount,
                'discount_amount'       => $discountAmount,
                'total_amount'          => $totalAmount,
                'notes'                 => $request->notes,
                'queue_number'          => $this->generateQueueNumber($branch->id),
                'completed_at'          => now(),
            ]);

            // ── Create order items — no modifiers table for coffee POS ─────
            foreach ($itemsData as $itemData) {
                unset($itemData['_customizations']); // never persisted
                OrderItem::create([
                    'order_id' => $order->id,
                    ...$itemData,
                ]);
            }

            // ── Payment record ─────────────────────────────────────────────
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
                'currency'       => $branch->tenant->currency ?? 'USD',
                'status'         => 'completed',
                'paid_at'        => now(),
            ]);

            // ── Status history ─────────────────────────────────────────────
            OrderStatusHistory::create([
                'order_id'    => $order->id,
                'from_status' => null,
                'to_status'   => 'completed',
                'notes'       => "Coffee POS sale · {$cashier->email}",
            ]);

            $order->load('items');

            return response()->json([
                'success' => true,
                'data'    => [
                    'id'                   => $order->id,
                    'order_number'         => $order->order_number,
                    'queue_number'         => $order->queue_number,
                    'queue_number_display' => '#' . $order->queue_number,
                    'subtotal'             => (float) $order->subtotal,
                    'discount'             => (float) $order->discount_amount,
                    'tax'                  => (float) $order->tax_amount,
                    'service_charge'       => (float) $order->service_charge_amount,
                    'total_amount'         => (float) $order->total_amount,
                    'payment_method'       => $request->payment_method,
                    'cash_tendered'        => $cashTendered,
                    'change_given'         => $changeGiven,
                    'payment_id'           => $payment->id,
                    'prints'               => $this->buildPrints(
                        $order,
                        $payment,
                        $branch,
                        $cashier,
                        $itemsData   // carry customizations from memory
                    ),
                ],
            ], 201);
        });
    }

    // ── Parse customizations — accepts any shape ───────────────────────────
    // Handles: null, [], {}, JSON string, key-value object, array of objects
    private function parseCustomizations(mixed $raw): array
    {
        if (empty($raw)) return [];

        // Decode if JSON string
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?? [];
        }

        if (!is_array($raw)) return [];

        $result = [];

        foreach ($raw as $key => $value) {
            if ($value === null || $value === '' || $value === false) continue;

            // Already shaped as { label, value } — pass through
            if (is_array($value) && isset($value['label'], $value['value'])) {
                $result[] = $value;
                continue;
            }

            // Key-value object: { sugar: "No Sugar", variant_name: "M" }
            if (is_string($key)) {
                $result[] = [
                    'label' => $key,
                    'value' => (string) $value,
                ];
                continue;
            }

            // Indexed array of strings: ["No Sugar", "Oat Milk"]
            if (is_string($value)) {
                $result[] = [
                    'label' => null,
                    'value' => $value,
                ];
            }
        }

        return $result;
    }

    // ── Build all 3 print payloads ─────────────────────────────────────────
    private function buildPrints(
        Order $order,
        Payment $payment,
        Branch $branch,
        $cashier,
        array $itemsData
    ): array {
        return [
            'order_ticket' => $this->buildOrderTicket($order, $cashier, $itemsData),
            'queue_ticket' => $this->buildQueueTicket($order),
            'receipt'      => $this->buildReceipt($order, $payment, $branch, $cashier, $itemsData),
        ];
    }

    // ── 1. Order ticket — bar/counter, shows what to make ─────────────────
    private function buildOrderTicket(Order $order, $cashier, array $itemsData): array
    {
        return [
            'type'                 => 'order_ticket',
            'order_number'         => $order->order_number,
            'queue_number'         => $order->queue_number,
            'queue_number_display' => '#' . $order->queue_number,
            'cashier'              => $cashier->email,
            'time'                 => now()->format('h:i A'),
            'date'                 => now()->format('d/m/Y'),
            'notes'                => $order->notes,
            'items'                => collect($itemsData)->map(fn($i) => [
                'name'           => $i['product_name'],
                'quantity'       => $i['quantity'],
                'customizations' => $i['_customizations'] ?? [],
                // → [{ label: "sugar", value: "No Sugar" }, { label: "variant_name", value: "M" }]
                'note'           => $i['notes'],
            ]),
        ];
    }

    // ── 2. Queue ticket — customer facing ─────────────────────────────────
    private function buildQueueTicket(Order $order): array
    {
        return [
            'type'                 => 'queue_ticket',
            'queue_number'         => $order->queue_number,
            'queue_number_display' => '#' . $order->queue_number,
            'order_number'         => $order->order_number,
            'item_count'           => $order->items->sum('quantity'),
            'time'                 => now()->format('h:i A'),
            'date'                 => now()->format('d/m/Y'),
        ];
    }

    // ── 3. Receipt — full financials ───────────────────────────────────────
    private function buildReceipt(
        Order $order,
        Payment $payment,
        Branch $branch,
        $cashier,
        array $itemsData
    ): array {
        return [
            'type'                 => 'receipt',
            'order_number'         => $order->order_number,
            'queue_number'         => $order->queue_number,
            'queue_number_display' => '#' . $order->queue_number,
            'branch_name'          => $branch->name,
            'branch_address'       => $branch->address ?? null,
            'branch_phone'         => $branch->phone   ?? null,
            'cashier'              => $cashier->email,
            'date'                 => now()->toDateTimeString(),
            'items'                => collect($itemsData)->map(fn($i) => [
                'name'           => $i['product_name'],
                'unit'           => $i['unit_name'],
                'qty'            => $i['quantity'],
                'unit_price'     => (float) $i['unit_price'],
                'total_price'    => (float) $i['total_price'],
                'customizations' => $i['_customizations'] ?? [],
                'note'           => $i['notes'],
            ]),
            'subtotal'             => (float) $order->subtotal,
            'discount'             => (float) $order->discount_amount,
            'tax'                  => (float) $order->tax_amount,
            'service_charge'       => (float) $order->service_charge_amount,
            'total'                => (float) $order->total_amount,
            'payment_method'       => $payment->payment_method,
            'cash_tendered'        => (float) ($payment->amount + ($payment->change_given ?? 0)),
            'change_given'         => (float) ($payment->change_given ?? 0),
            'printed_at'           => now()->format('d/m/Y H:i'),
        ];
    }

    // ── Queue number generator ─────────────────────────────────────────────
    private function generateQueueNumber(string $branchId): int
    {
        $last = Order::where('branch_id', $branchId)
            ->whereDate('created_at', now()->toDateString())
            ->whereNotNull('queue_number')
            ->orderByDesc('queue_number')
            ->lockForUpdate()
            ->value('queue_number');

        return ($last ?? 0) + 1;
    }
}
