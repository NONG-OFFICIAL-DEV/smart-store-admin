<?php

namespace App\Http\Controllers\Api;

use App\Events\NewOrderPlaced;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\KitchenDisplayTicket;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Table;
use App\Services\TableSessionService;
use App\Services\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(
        private TenantResolver $tenantResolver,
        private TableSessionService $tableSessions
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 10), 100);
        $query = Order::with([
            'items.product',
            'diningTable',
        ]);
        if ($search = $request->get('search')) {
            $query->where('order_number', 'like', "%{$search}%")
                ->orWhere('status', 'like', "%{$search}%");
        }
        $query->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_order', 'desc'));
        $items = $query->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Orders retrieved successfully.',
            'data'    => $items,
        ], 200);
    }


    // ── POST /api/v1/public/orders ─────────────────────────────────────────
    // Called from mobile menu — no auth required
    public function store(Request $request)
    {
        $request->validate([
            'branch_id'        => 'required|uuid|exists:branches,id',
            'table_id'         => 'nullable|uuid|exists:tables,id',
            'order_type'       => 'in:dine_in,takeaway,delivery,online',
            'notes'            => 'nullable|string|max:500',
            'items'            => 'required|array|min:1',
            'items.*.product_id' => 'required|uuid|exists:products,id',
            'items.*.variant_id' => 'nullable|uuid|exists:product_variants,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.notes'      => 'nullable|string|max:200',
            'items.*.modifiers'  => 'nullable|array',
            'items.*.modifiers.*.modifier_option_id' => 'uuid|exists:modifier_options,id',
            'items.*.modifiers.*.quantity'           => 'integer|min:1',
        ]);

        $branch = Branch::with('tenant.businessType')->findOrFail($request->branch_id);

        return DB::transaction(function () use ($request, $branch) {

            // ── Calculate totals ───────────────────────────────────────────
            $subtotal = 0;
            $itemsData = [];

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $variant = isset($item['variant_id'])
                    ? ProductVariant::find($item['variant_id'])
                    : null;

                // Base price + variant adjustment
                $unitPrice = (float) $product->base_price
                    + (float) ($variant?->price_adjustment ?? 0);

                // Add modifier prices
                $modifierTotal = 0;
                $modifiersData = [];

                foreach ($item['modifiers'] ?? [] as $mod) {
                    $option = ModifierOption::findOrFail($mod['modifier_option_id']);
                    $modQty = $mod['quantity'] ?? 1;
                    $modifierTotal += (float) $option->price * $modQty;

                    $modifiersData[] = [
                        'modifier_option_id' => $option->id,
                        'option_name'        => $option->name,   // snapshot
                        'price_adjustment'   => $option->price,
                        'quantity'           => $modQty,
                    ];
                }

                $unitPrice  += $modifierTotal;
                $totalPrice  = $unitPrice * $item['quantity'];
                $subtotal   += $totalPrice;

                $itemsData[] = [
                    'product_id'      => $product->id,
                    'variant_id'      => $variant?->id,
                    'product_name'    => $product->name,   // snapshot
                    'quantity'        => $item['quantity'],
                    'unit_price'      => $unitPrice,
                    'discount_amount' => 0,
                    'total_price'     => $totalPrice,
                    'notes'           => $item['notes'] ?? null,
                    'status'          => 'pending',
                    'modifiers'       => $modifiersData,
                ];
            }

            // ── Calculate tax + service charge ─────────────────────────────
            $taxRate            = (float) $branch->tax_rate;
            $serviceChargeRate  = (float) ($branch->service_charge_rate ?? 0);
            $taxAmount          = round($subtotal * $taxRate, 2);
            $serviceChargeAmount = round($subtotal * $serviceChargeRate, 2);
            $totalAmount        = $subtotal + $taxAmount + $serviceChargeAmount;

            $orderType = $request->order_type ?? 'dine_in';

            // A customer scanning the QR code at their table and ordering
            // joins that table's running tab (opening one if none is open
            // yet) — occupies the table as a side effect. Staff frees it
            // manually from the Tables page once the customer actually
            // leaves (same pattern the POS checkout controllers use).
            $session = $orderType === 'dine_in' && $request->table_id
                ? $this->tableSessions->getOrCreateOpenSession($request->table_id)
                : null;

            // ── Create order ───────────────────────────────────────────────
            $order = Order::create([
                'branch_id'              => $branch->id,
                'table_id'               => $request->table_id,
                'table_session_id'       => $session?->id,
                'order_type'             => $orderType,
                'status'                 => 'pending',
                'source'                 => 'mobile_app',
                'subtotal'               => $subtotal,
                'tax_amount'             => $taxAmount,
                'service_charge_amount'  => $serviceChargeAmount,
                'discount_amount'        => 0,
                'total_amount'           => $totalAmount,
                'notes'                  => $request->notes,
                'queue_number'           => $this->generateQueueNumber($branch->id),
            ]);

            // ── Create order items + modifiers ─────────────────────────────
            foreach ($itemsData as $itemData) {
                $modifiers = $itemData['modifiers'];
                unset($itemData['modifiers']);

                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    ...$itemData,
                ]);

                foreach ($modifiers as $mod) {
                    OrderItemModifier::create([
                        'order_item_id' => $orderItem->id,
                        ...$mod,
                    ]);
                }
            }

            // ── Log status history ─────────────────────────────────────────
            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'from_status' => null,
                'to_status'   => 'pending',
                'notes'       => 'Order placed from mobile app',
            ]);

            // Food tenants need a kitchen ticket for every order so it shows
            // up on the Kitchen Display without a staff member manually
            // looking it up by order number. Mart/retail tenants have no
            // prep step, so no ticket is created for them.
            if ($branch->tenant->businessType?->category === 'food') {
                KitchenDisplayTicket::create([
                    'order_id'  => $order->id,
                    'branch_id' => $branch->id,
                ]);
            }

            $order->load([
                'items.product',
                'items.modifiers',
                'diningTable',
                'statusHistory',
            ]);

            $formatted = $this->formatOrder($order);

            // Live push to Kitchen Display / Orders Management — see
            // NewOrderPlaced + routes/channels.php for the branch-scoped
            // private channel both screens subscribe to. ShouldBroadcastNow
            // dispatches synchronously inline here, so if Reverb is ever
            // unreachable (down, restarting, network blip) this must not
            // take the whole order down with it — staff just miss the
            // live ping and fall back to the existing poll/manual refresh.
            try {
                event(new NewOrderPlaced($branch->id, $formatted));
            } catch (\Throwable $e) {
                report($e);
            }

            return response()->json([
                'success' => true,
                'data'    => $formatted,
            ], 201);
        });
    }
    /**
     * Display the specified resource.
     */
    public function show(string $identifier)
    {
        $order = Order::with([
            'items.product',
            'items.variant',
            'items.modifiers',
            'diningTable',
            'statusHistory',
            'payments',
            'cashier.user',
        ])
            ->where(function ($q) use ($identifier) {
                $q->where('order_number', $identifier)
                    ->orWhere('id', $identifier);
            })
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => $this->formatOrder($order),
        ]);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        return Order::store($request, $id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    // ── GET /api/v1/public/orders/table/{tableId} ──────────────────────────
    // Get active order for a table (used on page load)
    public function byTable(string $tableId)
    {
        $order = Order::with([
            'items.product',
            'items.variant',
            'items.modifiers',
            'statusHistory',
            'diningTable',
        ])
            ->where('table_id', $tableId)
            // Coffee/Mart POS sales are recorded 'completed' the instant
            // they're rung up (no running-tab lifecycle), but the table
            // stays occupied until staff frees it — so "completed" here
            // does not mean "nothing to show for this table" the way it
            // does for a running tab. Only cancelled/refunded orders are
            // genuinely dead and excluded; latest() still always returns
            // whichever order is actually current for the table.
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data'    => $order ? $this->formatOrder($order) : null,
        ]);
    }

    // ── GET /api/v1/tables/{tableId}/orders ──────────────────────────────────
    // A table can carry more than one separate open order per sitting —
    // a customer ordering again from the e-menu, or staff ringing up a
    // second round on the POS, each creates its own Order (the app
    // deliberately doesn't merge repeat orders into one running tab — see
    // CoffeePOSorderController/OrderController::store()). byTable() above
    // only ever surfaces the single latest one, which is what hid earlier
    // tickets from Table Management's "manage order" view entirely. This
    // returns every still-open order for the table instead — see
    // openOrdersForTable() for exactly what "open" means here.
    public function ordersByTable(string $tableId)
    {
        $orders = $this->openOrdersForTable($tableId)->get();

        return response()->json([
            'success' => true,
            'data'    => $orders->map(fn($order) => $this->formatOrder($order))->values(),
        ]);
    }

    // ── GET /api/v1/tables/{table}/bill ─────────────────────────────────────
    // The session-based merged bill for a table's current running tab —
    // see TableSessionService::getSessionBill(). {session: null} for an
    // unoccupied table is a normal response, never a 404.
    public function bill(Table $table)
    {
        $session = $this->tableSessions->getOpenSession($table->id);
        if (!$session) {
            return response()->json(['success' => true, 'data' => ['session' => null]]);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->tableSessions->getSessionBill($session->id),
        ]);
    }

    // ── Single source of truth: which orders at a table still need paying ──
    // "Open" = not cancelled/refunded AND not yet paid (no Payment row —
    // this schema never does partial payments, so any Payment row means
    // the order is fully settled). Table Management's combined order view
    // and the Pay/Print Bill actions it drives all go through this one
    // query so a future call site can't forget the unpaid filter and
    // re-merge an already-closed order back into a table's total — which
    // is exactly the bug this method fixes (a paid order was previously
    // still showing up here, double-counting the table's balance).
    private function openOrdersForTable(string $tableId)
    {
        return Order::with([
            'items.product',
            'items.variant',
            'items.modifiers',
            'statusHistory',
            'payments',
            'diningTable',
            'cashier.user',
        ])
            ->where('table_id', $tableId)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->whereDoesntHave('payments')
            ->latest();
    }

    // ── PATCH /api/v1/orders/{order}/settle-payment ─────────────────────────
    // Collects payment on a 'pay later' order rung up with no Payment row
    // (CoffeePOSorderController/MartPosController's pay_later path) — the
    // one place a deferred sale actually gets marked paid. A dine-in order
    // that belongs to an open table session is never paid on its own —
    // paying it means paying the WHOLE session (every other unpaid order at
    // that table too), so this delegates to TableSessionService::paySession()
    // instead. This is what makes the Orders page's Settle Payment button
    // correct for a dine-in order with session-mates, without needing its
    // own separate UI yet (that's the Pay dialog consolidation, still to come).
    public function settlePayment(Request $request, Order $order)
    {
        if ($order->table_session_id) {
            $validated = $request->validate([
                'payment_method' => 'required|in:cash,card,qr,transfer',
                'cash_tendered'  => 'nullable|numeric|min:0',
            ]);

            try {
                $receipt = $this->tableSessions->paySession(
                    $order->table_session_id,
                    $validated,
                    auth()->user()?->staff?->id
                );
            } catch (\App\Exceptions\PaySessionException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode());
            }

            return response()->json(['success' => true, 'message' => 'Payment recorded.', 'data' => $receipt]);
        }

        if ($order->payments()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This order already has a payment recorded.',
            ], 422);
        }

        if (in_array($order->status, ['cancelled', 'refunded'])) {
            return response()->json([
                'success' => false,
                'message' => "A {$order->status} order can't be paid.",
            ], 422);
        }

        $validated = $request->validate([
            'payment_method' => 'required|in:cash,card,qr,transfer',
            'cash_tendered'  => 'nullable|numeric|min:0',
        ]);

        if (
            $validated['payment_method'] === 'cash'
            && isset($validated['cash_tendered'])
            && (float) $validated['cash_tendered'] < (float) $order->total_amount
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Cash tendered is less than the order total.',
            ], 422);
        }

        $paymentMethodMap = [
            'cash'     => 'cash',
            'card'     => 'card',
            'qr'       => 'qr_code',
            'transfer' => 'online',
        ];

        return DB::transaction(function () use ($validated, $paymentMethodMap, $order) {
            $staff        = auth()->user()?->staff;
            $cashTendered = (float) ($validated['cash_tendered'] ?? $order->total_amount);
            $changeGiven  = $validated['payment_method'] === 'cash'
                ? max(0, $cashTendered - (float) $order->total_amount)
                : null;

            // Stamped so a receipt reprinted after the tenant changes their
            // rate still shows the rate that was actually in effect here.
            $exchangeRate = (float) ($order->branch->tenant->pos_settings['khr_exchange_rate']
                ?? \App\Models\Tenant::DEFAULT_POS_SETTINGS['khr_exchange_rate']);

            $payment = Payment::create([
                'order_id'       => $order->id,
                'branch_id'      => $order->branch_id,
                'staff_id'       => $staff?->id,
                'payment_method' => $paymentMethodMap[$validated['payment_method']],
                'amount'         => $order->total_amount,
                'change_given'   => $changeGiven,
                'currency'       => $order->branch->tenant->currency ?? 'USD',
                'exchange_rate'  => $exchangeRate,
                'status'         => 'completed',
                'paid_at'        => now(),
            ]);

            Order::updateStatus(
                $order->id,
                'completed',
                $staff?->id,
                'Payment settled · ' . (auth()->user()?->email ?? 'system')
            );
            $order->refresh();

            $order->load(['items.product', 'items.variant', 'items.modifiers', 'diningTable', 'statusHistory', 'payments', 'cashier.user']);

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded.',
                'data'    => $this->formatOrder($order),
            ]);
        });
    }

    private function generateQueueNumber(string $branchId): int
    {
        $today = now()->startOfDay();
        $last  = Order::where('branch_id', $branchId)
            ->where('created_at', '>=', $today)
            ->max('queue_number') ?? 0;
        return $last + 1;
    }

    private function formatOrder(Order $order): array
    {
        return [
            'id'                     => $order->id,
            'order_number'           => $order->order_number,
            'order_date'           => $order->created_date,
            'queue_number'           => $order->queue_number,
            'order_type'             => $order->order_type,
            'status'                 => $order->status,
            'table_session_id'       => $order->table_session_id,
            'table' => $order->relationLoaded('diningTable') && $order->diningTable ? [
                'id'     => $order->diningTable->id,
                'number' => $order->diningTable->table_number,
            ] : null,
            'subtotal'               => (float) $order->subtotal,
            'discount_amount'        => (float) $order->discount_amount,
            'tax_amount'             => (float) $order->tax_amount,
            'service_charge_amount'  => (float) $order->service_charge_amount,
            'total_amount'           => (float) $order->total_amount,
            'notes'                  => $order->notes,
            'estimated_ready_at'     => $order->estimated_ready_at,
            'created_at'             => $order->created_at,
            'cashier'                => $order->relationLoaded('cashier') && $order->cashier?->user
                ? $order->cashier->user->full_name
                : null,
            'items'                  => $order->items->map(fn($item) => [
                'id'           => $item->id,
                'product_name' => $item->product_name,
                'image_url' => $item->product?->image_url,
                'quantity'     => $item->quantity,
                'unit_price'   => (float) $item->unit_price,
                'unit_name'   =>  $item->unit_name,
                'total_price'  => (float) $item->total_price,
                'status'       => $item->status,
                'notes'        => $item->notes,
                'modifiers'    => $item->modifiers->map(fn($m) => [
                    'name'  => $m->option_name,
                    'price' => (float) $m->price_adjustment,
                ]),
            ]),
            'status_history' => $order->statusHistory->map(fn($h) => [
                'from'       => $h->from_status,
                'to'         => $h->to_status,
                'notes'      => $h->notes,
                'changed_at' => $h->changed_at,
            ]),
            // POS checkout (CoffeePOSorderController etc.) pays in full the
            // instant the order is rung up — one payment per order in
            // practice, so the latest one is "the" payment.
            'payment' => $order->relationLoaded('payments') && $order->payments->isNotEmpty() ? (function () use ($order) {
                $payment = $order->payments->last();
                return [
                    'method'         => $payment->payment_method,
                    'cash_tendered'  => (float) $payment->amount + (float) ($payment->change_given ?? 0),
                    'change_given'   => (float) ($payment->change_given ?? 0),
                    'exchange_rate'  => $payment->exchange_rate !== null ? (float) $payment->exchange_rate : null,
                    'paid_at'        => $payment->paid_at,
                ];
            })() : null,
        ];
    }

    // order report with filters + stats for mart store
    public function orderReport(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 10), 100);
        $user    = auth()->user();

        // ── Resolve allowed branch IDs for this user ──────────────────────────
        $allowedBranchIds = null;
        if (!$user->is_super_admin) {
            $tenantId         = $this->tenantResolver->resolve($request);
            $allowedBranchIds = \App\Models\Branch::where('tenant_id', $tenantId)
                ->pluck('id')
                ->toArray();
        }

        // ── Branch IDs requested by the frontend (branch_ids[] array) ─────────
        $requestedBranchIds = $request->input('branch_ids', []);

        // ── Final branch filter: intersection of allowed + requested ──────────
        if (!empty($requestedBranchIds) && $allowedBranchIds !== null) {
            $finalBranchIds = array_intersect($requestedBranchIds, $allowedBranchIds);
        } elseif (!empty($requestedBranchIds)) {
            $finalBranchIds = $requestedBranchIds; // super admin scoping to specific branches
        } else {
            $finalBranchIds = $allowedBranchIds; // all tenant branches
        }

        // ── Shared date/branch closure to avoid repeating filters ─────────────
        $applyBaseFilters = function ($q) use ($finalBranchIds, $request) {
            $q->when($finalBranchIds,      fn($q) => $q->whereIn('branch_id', $finalBranchIds))
                ->when($request->date_from,  fn($q) => $q->where('created_at', '>=', \Carbon\Carbon::parse($request->date_from)->startOfDay()))
                ->when($request->date_to,    fn($q) => $q->where('created_at', '<=', \Carbon\Carbon::parse($request->date_to)->endOfDay()));
        };

        // ── Paginated order list ───────────────────────────────────────────────
        $query = Order::with([
            'branch:id,name',
            'customer:id,name,phone',
            'items',
            'payments',
        ])
            ->when($finalBranchIds,       fn($q) => $q->whereIn('branch_id', $finalBranchIds))
            ->when($request->status,      fn($q) => $q->where('status', $request->status))
            ->when($request->order_type,  fn($q) => $q->where('order_type', $request->order_type))
            ->when($request->payment_method, fn($q) => $q->where('payment_method', $request->payment_method))
            ->when($request->date_from,   fn($q) => $q->where('created_at', '>=', \Carbon\Carbon::parse($request->date_from)->startOfDay()))
            ->when($request->date_to,     fn($q) => $q->where('created_at', '<=', \Carbon\Carbon::parse($request->date_to)->endOfDay()))
            ->when(
                $request->search,
                fn($q) =>
                $q->where(
                    fn($q2) =>
                    $q2->where('order_number', 'like', "%{$request->search}%")
                        ->orWhereHas(
                            'customer',
                            fn($q3) =>
                            $q3->where('name',  'like', "%{$request->search}%")
                                ->orWhere('phone', 'like', "%{$request->search}%")
                        )
                )
            )
            ->orderBy('created_at', 'desc');

        $orders = $query->paginate($perPage);

        // ── Stats base query (non-cancelled, branch + date filters) ───────────
        $statsQuery = Order::where('status', '!=', 'cancelled');
        $applyBaseFilters($statsQuery);

        $totalOrders  = (clone $statsQuery)->count();
        $totalRevenue = (clone $statsQuery)->sum('total_amount');

        $stats = [
            'total_orders'     => $totalOrders,
            'total_revenue'    => round($totalRevenue, 2),
        ];

        return response()->json([
            'success' => true,
            'data'    => $orders,
            'stats'   => $stats,
        ]);
    }
}
