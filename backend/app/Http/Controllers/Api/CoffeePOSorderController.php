<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
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
            'branch_id'                                   => 'required|uuid|exists:branches,id',
            'payment_method'                              => 'required|in:cash,card,qr,transfer',
            'cash_tendered'                               => 'nullable|numeric|min:0',
            'change_given'                                => 'nullable|numeric|min:0',
            'notes'                                       => 'nullable|string|max:500',
            'discount_amount'                             => 'nullable|numeric|min:0',
            'items'                                       => 'required|array|min:1',
            'items.*.product_id'                          => 'required|uuid|exists:products,id',
            'items.*.quantity'                            => 'required|integer|min:1',
            'items.*.price'                               => 'nullable|numeric|min:0',
            'items.*.note'                                => 'nullable|string|max:200',
            'items.*.customizations'                      => 'nullable|array',
            'items.*.customizations.*.modifier_option_id' => 'uuid|exists:modifier_options,id',
            'items.*.customizations.*.quantity'           => 'integer|min:1',
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
            // ── Calculate totals ───────────────────────────────────────────
            $subtotal  = 0;
            $itemsData = [];

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);

                // Always trust DB price; fall back to client price only if base_price is null
                $unitPrice = (float) ($product->base_price ?? $item['price'] ?? 0);

                // ── Customizations (modifier options) ──────────────────────
                $modifierTotal      = 0;
                $customizationsData = [];

                foreach ($item['customizations'] ?? [] as $custom) {
                    $option  = ModifierOption::findOrFail($custom['modifier_option_id']);
                    $modQty  = $custom['quantity'] ?? 1;
                    $modifierTotal += (float) $option->price * $modQty;

                    $customizationsData[] = [
                        'modifier_option_id' => $option->id,
                        'option_name'        => $option->name,   // snapshot
                        'price_adjustment'   => (float) $option->price,
                        'quantity'           => $modQty,
                    ];
                }

                $unitPrice  += $modifierTotal;
                $totalPrice  = $unitPrice * $item['quantity'];
                $subtotal   += $totalPrice;

                $itemsData[] = [
                    'product_id'      => $product->id,
                    'product_name'    => $product->name,         // snapshot
                    'unit_name'       => $product->unit ?? 'cup',
                    'quantity'        => $item['quantity'],
                    'unit_price'      => $unitPrice,
                    'discount_amount' => 0,
                    'total_price'     => $totalPrice,
                    'notes'           => $item['note'] ?? null,
                    'status'          => 'served',
                    '_customizations' => $customizationsData,    // temp key, stripped before insert
                ];
            }

            // ── Totals ─────────────────────────────────────────────────────
            $discountAmount      = (float) ($request->discount_amount ?? 0);
            $taxRate             = (float) ($branch->tax_rate ?? $branch->tenant->tax_rate ?? 0);
            $serviceChargeRate   = (float) ($branch->service_charge_rate ?? 0);
            $taxAmount           = round(($subtotal - $discountAmount) * $taxRate, 2);
            $serviceChargeAmount = round($subtotal * $serviceChargeRate, 2);
            $totalAmount         = $subtotal - $discountAmount + $taxAmount + $serviceChargeAmount;

            // ── Create order ───────────────────────────────────────────────
            $order = Order::create([
                'branch_id'              => $branch->id,
                'cashier_id'             => $staff?->id,
                'order_type'             => 'takeaway',
                'status'                 => 'completed',
                'source'                 => 'pos',
                'subtotal'               => $subtotal,
                'tax_amount'             => $taxAmount,
                'service_charge_amount'  => $serviceChargeAmount,
                'discount_amount'        => $discountAmount,
                'total_amount'           => $totalAmount,
                'notes'                  => $request->notes,
                'queue_number'           => $this->generateQueueNumber($branch->id),
                'completed_at'           => now(),
            ]);

            // ── Create order items + customizations ────────────────────────
            foreach ($itemsData as $itemData) {
                $customizations = $itemData['_customizations'];
                unset($itemData['_customizations']);

                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    ...$itemData,
                ]);

                foreach ($customizations as $custom) {
                    OrderItemModifier::create([
                        'order_item_id' => $orderItem->id,
                        ...$custom,
                    ]);
                }
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

            $order->load('items.modifiers');

            return response()->json([
                'success' => true,
                'data'    => [
                    'id'             => $order->id,
                    'order_number'   => $order->order_number,
                    'queue_number'   => $order->queue_number,
                    'queue_number_display' => '#' . $order->queue_number,
                    'subtotal'       => (float) $order->subtotal,
                    'discount'       => (float) $order->discount_amount,
                    'tax'            => (float) $order->tax_amount,
                    'service_charge' => (float) $order->service_charge_amount,
                    'total_amount'   => (float) $order->total_amount,
                    'payment_method' => $request->payment_method,
                    'cash_tendered'  => $cashTendered,
                    'change_given'   => $changeGiven,
                    'payment_id'     => $payment->id,
                    'prints'         => $this->buildPrints($order, $payment, $branch, $cashier),
                ],
            ], 201);
        });
    }

    // ── Build all 3 print payloads ─────────────────────────────────────────
    private function buildPrints(Order $order, Payment $payment, Branch $branch, $cashier): array
    {
        return [
            'order_ticket' => $this->buildOrderTicket($order, $cashier),
            'queue_ticket' => $this->buildQueueTicket($order),
            'receipt'      => $this->buildReceipt($order, $payment, $branch, $cashier),
        ];
    }

    // ── 1. Order ticket ────────────────────────────────────────────────────
    // Printed at the bar/counter — shows what to make
    private function buildOrderTicket(Order $order, $cashier): array
    {
        return [
            'type'         => 'order_ticket',
            'order_number' => $order->order_number,
            'queue_number' => $order->queue_number,
            'queue_number_display' => '#' . $order->queue_number,
            'cashier'      => $cashier->email,
            'time'         => now()->format('h:i A'),
            'date'         => now()->format('d/m/Y'),
            'notes'        => $order->notes,
            'items'        => $order->items->map(fn($i) => [
                'name'           => $i->product_name,
                'quantity'       => $i->quantity,
                'customizations' => $i->modifiers
                    ->map(fn($m) => $m->option_name)
                    ->values(),
                'note'           => $i->notes,
            ]),
        ];
    }

    // ── 2. Queue ticket ────────────────────────────────────────────────────
    // Given to the customer — shows their queue number
    private function buildQueueTicket(Order $order): array
    {
        return [
            'type'         => 'queue_ticket',
            'queue_number' => $order->queue_number,
            'queue_number_display' => '#' . $order->queue_number,
            'order_number' => $order->order_number,
            'item_count'   => $order->items->sum('quantity'),
            'time'         => now()->format('h:i A'),
            'date'         => now()->format('d/m/Y'),
        ];
    }

    // ── 3. Payment receipt ─────────────────────────────────────────────────
    // Full financial breakdown for the customer
    private function buildReceipt(Order $order, Payment $payment, Branch $branch, $cashier): array
    {
        return [
            'type'           => 'receipt',
            'order_number'   => $order->order_number,
            'queue_number'   => $order->queue_number,
            'queue_number_display' => '#' . $order->queue_number,
            'branch_name'    => $branch->name,
            'branch_address' => $branch->address ?? null,
            'branch_phone'   => $branch->phone   ?? null,
            'cashier'        => $cashier->email,
            'date'           => now()->toDateTimeString(),
            'items'          => $order->items->map(fn($i) => [
                'name'           => $i->product_name,
                'unit'           => $i->unit_name,
                'qty'            => $i->quantity,
                'unit_price'     => (float) $i->unit_price,
                'total_price'    => (float) $i->total_price,
                'customizations' => $i->modifiers->map(fn($m) => [
                    'option_name'      => $m->option_name,
                    'price_adjustment' => (float) $m->price_adjustment,
                    'quantity'         => $m->quantity,
                ]),
                'note'           => $i->notes,
            ]),
            'subtotal'       => (float) $order->subtotal,
            'discount'       => (float) $order->discount_amount,
            'tax'            => (float) $order->tax_amount,
            'service_charge' => (float) $order->service_charge_amount,
            'total'          => (float) $order->total_amount,
            'payment_method' => $payment->payment_method,
            'cash_tendered'  => (float) ($payment->amount + ($payment->change_given ?? 0)),
            'change_given'   => (float) ($payment->change_given ?? 0),
            'printed_at'     => now()->format('d/m/Y H:i'),
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
            ->value('queue_number');  // pulls the single highest value, lock is valid on row fetch

        return ($last ?? 0) + 1;
    }
}
