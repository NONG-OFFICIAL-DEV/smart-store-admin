<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Services\TenantResolver;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private TenantResolver $tenantResolver
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage  = min((int) $request->get('per_page', 15), 100);
        $user     = auth()->user();
        $tenantId = $this->tenantResolver->resolve($request);

        $orders = PurchaseOrder::with([
            'supplier:id,name',
            'branch:id,name',
            'items',
        ])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->when($request->branch_id,  fn($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->supplier_id, fn($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->status,     fn($q) => $q->where('status', $request->status))
            ->when(
                $request->search,
                fn($q) =>
                $q->where('po_number', 'like', "%{$request->search}%")
            )
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json(['success' => true, 'data' => $orders]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        return PurchaseOrder::store($request);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $tenantId = $this->tenantResolver->resolve($request);

        $order = PurchaseOrder::with([
            'supplier:id,name,phone,email,contact_person',
            'branch:id,name',
            'items.ingredient:id,name,unit',
        ])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $order]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        return PurchaseOrder::store($request, $id);
    }

    // ── Receive items (partial or full) ───────────────────────────────────────
    public function receive(Request $request, string $id)
    {
        $request->validate([
            'items'                      => 'required|array|min:1',
            'items.*.id'                 => 'required|uuid|exists:purchase_order_items,id',
            'items.*.quantity_received'  => 'required|numeric|min:0',
        ]);

        $order = PurchaseOrder::with('items.ingredient')->findOrFail($id);

        if ($order->status === 'received' || $order->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'PO is already ' . $order->status,
            ], 422);
        }

        foreach ($request->items as $recv) {
            $item = $order->items->firstWhere('id', $recv['id']);
            if (!$item) continue;

            $qtyToReceive = min(
                $recv['quantity_received'],
                $item->quantity_ordered - $item->quantity_received // max remaining
            );

            if ($qtyToReceive <= 0) continue;

            $item->update([
                'quantity_received' => $item->quantity_received + $qtyToReceive,
                'received_at'       => now(),
            ]);

            // Update ingredient stock
            $ingredient = $item->ingredient;
            if ($ingredient) {
                $ingredient->increment('stock_quantity', $qtyToReceive);
            }
        }

        // Recalculate status
        $order->refresh();
        $allReceived      = $order->items->every(fn($i) => $i->quantity_received >= $i->quantity_ordered);
        $anyReceived      = $order->items->some(fn($i)  => $i->quantity_received > 0);
        $newStatus        = $allReceived ? 'received' : ($anyReceived ? 'partially_received' : $order->status);
        $order->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => 'Items received successfully',
            'data'    => $order->fresh(['supplier:id,name', 'branch:id,name', 'items.ingredient:id,name,unit']),
        ]);
    }

    // ── Cancel ────────────────────────────────────────────────────────────────
    public function cancel(string $id)
    {
        $order = PurchaseOrder::findOrFail($id);

        if (in_array($order->status, ['received', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel a PO that is already ' . $order->status,
            ], 422);
        }

        $order->update(['status' => 'cancelled']);

        return response()->json(['success' => true, 'message' => 'PO cancelled']);
    }

    // ── Delete (draft only) ───────────────────────────────────────────────────
    public function destroy(string $id)
    {
        $order = PurchaseOrder::findOrFail($id);

        if ($order->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only draft POs can be deleted',
            ], 422);
        }

        $order->delete();

        return response()->json(['success' => true, 'message' => 'PO deleted']);
    }
}
