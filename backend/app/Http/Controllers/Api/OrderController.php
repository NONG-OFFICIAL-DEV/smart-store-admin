<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(
        private TenantResolver $tenantResolver
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

        $branch = Branch::with('tenant')->findOrFail($request->branch_id);

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

            // ── Create order ───────────────────────────────────────────────
            $order = Order::create([
                'branch_id'              => $branch->id,
                'table_id'               => $request->table_id,
                'order_type'             => $request->order_type ?? 'dine_in',
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

            $order->load(['items.modifiers', 'diningTable']);

            $order->load([
                'items.modifiers',
                'diningTable',
                'statusHistory',  // ← was missing
            ]);

            return response()->json([
                'success' => true,
                'data'    => $this->formatOrder($order),
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
        ])
            ->where('table_id', $tableId)
            ->whereNotIn('status', ['completed', 'cancelled', 'refunded'])
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data'    => $order ? $this->formatOrder($order) : null,
        ]);
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
