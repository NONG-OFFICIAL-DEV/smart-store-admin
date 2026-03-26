<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MartPurchaseOrder;
use App\Models\MartPurchaseOrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MartPurchaseOrderController extends Controller
{
    // ── GET /api/v1/mart/purchase-orders ─────────────────────────────────────
    public function index(Request $request)
    {
        $request->validate([
            'branch_id' => 'nullable|uuid|exists:branches,id',
            'status'    => 'nullable|string',
            'search'    => 'nullable|string',
            'per_page'  => 'nullable|integer|max:100',
        ]);

        $query = MartPurchaseOrder::with(['supplier:id,name', 'branch:id,name', 'items'])
            ->when($request->branch_id, fn($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->status,    fn($q) => $q->where('status', $request->status))
            ->when($request->search,    fn($q) =>
                $q->where('po_number', 'ilike', "%{$request->search}%")
                  ->orWhereHas('supplier', fn($s) =>
                      $s->where('name', 'ilike', "%{$request->search}%")
                  )
            )
            ->withCount('items')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 10);

        return response()->json(['success' => true, 'data' => $query]);
    }

    // ── GET /api/v1/mart/purchase-orders/{id} ────────────────────────────────
    public function show(string $id)
    {
        $po = MartPurchaseOrder::with([
            'supplier', 'branch:id,name',
            'items.product:id,name,image_url,stock_quantity,unit',
            'items.productUnit:id,unit_name,unit_label,qty_per_base',
        ])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $po]);
    }

    // ── POST /api/v1/mart/purchase-orders ────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'branch_id'         => 'required|uuid|exists:branches,id',
            'supplier_id'       => 'required|uuid|exists:suppliers,id',
            'expected_delivery' => 'nullable|date',
            'notes'             => 'nullable|string',
            'items'             => 'required|array|min:1',
            'items.*.product_id'      => 'required|uuid|exists:products,id',
            'items.*.product_unit_id' => 'nullable|uuid|exists:product_units,id',
            'items.*.quantity_ordered'=> 'required|numeric|min:0.001',
            'items.*.unit_cost'       => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($request) {
            $branch   = Branch::findOrFail($request->branch_id);
            $poNumber = $this->generatePoNumber($branch);

            $po = MartPurchaseOrder::create([
                'id'                  => Str::uuid(),
                'tenant_id'           => $branch->tenant_id,
                'branch_id'           => $request->branch_id,
                'supplier_id'         => $request->supplier_id,
                'po_number'           => $poNumber,
                'status'              => 'draft',
                'expected_delivery'   => $request->expected_delivery,
                'notes'               => $request->notes,
                'created_by_staff_id' => auth()->user()->staff?->id,
            ]);

            $total = 0;
            foreach ($request->items as $item) {
                $product     = Product::findOrFail($item['product_id']);
                $productUnit = isset($item['product_unit_id'])
                    ? \App\Models\ProductUnit::find($item['product_unit_id'])
                    : null;

                $totalCost = $item['unit_cost'] * $item['quantity_ordered'];
                $total    += $totalCost;

                MartPurchaseOrderItem::create([
                    'id'                      => Str::uuid(),
                    'mart_purchase_order_id'  => $po->id,
                    'product_id'              => $product->id,
                    'product_unit_id'         => $productUnit?->id,
                    'product_name'            => $product->name,
                    'unit_name'               => $productUnit?->unit_name ?? $product->unit ?? 'pcs',
                    'qty_per_base'            => $productUnit?->qty_per_base ?? 1,
                    'quantity_ordered'        => $item['quantity_ordered'],
                    'quantity_received'       => 0,
                    'unit_cost'               => $item['unit_cost'],
                    'total_cost'              => $totalCost,
                ]);
            }

            $po->update(['total_amount' => $total]);
            $po->load(['supplier:id,name', 'items']);

            return response()->json(['success' => true, 'data' => $po], 201);
        });
    }

    // ── PUT /api/v1/mart/purchase-orders/{id} ────────────────────────────────
    public function update(Request $request, string $id)
    {
        $po = MartPurchaseOrder::findOrFail($id);

        if (!in_array($po->status, ['draft', 'submitted'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft or submitted POs can be edited.',
            ], 422);
        }

        $request->validate([
            'supplier_id'       => 'sometimes|uuid|exists:suppliers,id',
            'expected_delivery' => 'nullable|date',
            'notes'             => 'nullable|string',
            'status'            => 'sometimes|in:draft,submitted,confirmed,cancelled',
            'items'             => 'sometimes|array|min:1',
            'items.*.product_id'      => 'required_with:items|uuid|exists:products,id',
            'items.*.product_unit_id' => 'nullable|uuid|exists:product_units,id',
            'items.*.quantity_ordered'=> 'required_with:items|numeric|min:0.001',
            'items.*.unit_cost'       => 'required_with:items|numeric|min:0',
        ]);

        return DB::transaction(function () use ($request, $po) {
            $po->update($request->only([
                'supplier_id', 'expected_delivery', 'notes', 'status'
            ]));

            if ($request->has('items')) {
                $po->items()->delete();
                $total = 0;

                foreach ($request->items as $item) {
                    $product     = Product::findOrFail($item['product_id']);
                    $productUnit = isset($item['product_unit_id'])
                        ? \App\Models\ProductUnit::find($item['product_unit_id'])
                        : null;

                    $totalCost = $item['unit_cost'] * $item['quantity_ordered'];
                    $total    += $totalCost;

                    MartPurchaseOrderItem::create([
                        'id'                      => Str::uuid(),
                        'mart_purchase_order_id'  => $po->id,
                        'product_id'              => $product->id,
                        'product_unit_id'         => $productUnit?->id,
                        'product_name'            => $product->name,
                        'unit_name'               => $productUnit?->unit_name ?? $product->unit ?? 'pcs',
                        'qty_per_base'            => $productUnit?->qty_per_base ?? 1,
                        'quantity_ordered'        => $item['quantity_ordered'],
                        'quantity_received'       => 0,
                        'unit_cost'               => $item['unit_cost'],
                        'total_cost'              => $totalCost,
                    ]);
                }
                $po->update(['total_amount' => $total]);
            }

            return response()->json(['success' => true, 'data' => $po->fresh(['items', 'supplier'])]);
        });
    }

    // ── POST /api/v1/mart/purchase-orders/{id}/receive ───────────────────────
    // Receive items → increment product.stock_quantity + log movement
    public function receive(Request $request, string $id)
    {
        $request->validate([
            'items'                    => 'required|array|min:1',
            'items.*.id'               => 'required|uuid|exists:mart_purchase_order_items,id',
            'items.*.quantity_received'=> 'required|numeric|min:0',
            'notes'                    => 'nullable|string',
        ]);

        $po = MartPurchaseOrder::with('items.product')->findOrFail($id);

        if (in_array($po->status, ['received', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'This PO is already received or cancelled.',
            ], 422);
        }

        return DB::transaction(function () use ($request, $po) {
            $staff = auth()->user()->staff;

            foreach ($request->items as $receivedItem) {
                $lineItem = $po->items->firstWhere('id', $receivedItem['id']);
                if (!$lineItem) continue;

                $qtyReceiving = (float) $receivedItem['quantity_received'];
                if ($qtyReceiving <= 0) continue;

                $remaining = $lineItem->quantity_ordered - $lineItem->quantity_received;
                $qtyReceiving = min($qtyReceiving, $remaining);

                // ── Update line item ─────────────────────────────────────
                $lineItem->increment('quantity_received', $qtyReceiving);
                $lineItem->update(['received_at' => now()]);

                // ── Increment product stock in BASE units ────────────────
                $baseQtyIn  = $qtyReceiving * $lineItem->qty_per_base;
                $product    = $lineItem->product;
                $qtyBefore  = (float) $product->stock_quantity;
                $qtyAfter   = $qtyBefore + $baseQtyIn;

                $product->increment('stock_quantity', $baseQtyIn);

                // ── Log stock movement ───────────────────────────────────
                StockMovement::create([
                    'id'             => Str::uuid(),
                    'branch_id'      => $po->branch_id,
                    'product_id'     => $product->id,
                    'movement_type'  => 'purchase',
                    'quantity'       => $baseQtyIn,
                    'qty_before'     => $qtyBefore,
                    'qty_after'      => $qtyAfter,
                    'unit_cost'      => $lineItem->unit_cost,
                    'reference_type' => 'mart_purchase_order',
                    'reference_id'   => $po->id,
                    'notes'          => $request->notes ?? "Received from PO {$po->po_number}",
                    'staff_id'       => $staff?->id,
                ]);
            }

            // ── Update PO status ─────────────────────────────────────────
            $po->refresh();
            $allReceived     = $po->items->every(fn($i) => $i->quantity_received >= $i->quantity_ordered);
            $anyReceived     = $po->items->some(fn($i)  => $i->quantity_received > 0);
            $po->status      = $allReceived ? 'received' : ($anyReceived ? 'partially_received' : $po->status);
            $po->save();

            return response()->json([
                'success' => true,
                'message' => $allReceived ? 'PO fully received.' : 'Partial receive recorded.',
                'data'    => $po->fresh(['items.product', 'supplier']),
            ]);
        });
    }

    // ── POST /api/v1/mart/purchase-orders/{id}/cancel ────────────────────────
    public function cancel(string $id)
    {
        $po = MartPurchaseOrder::findOrFail($id);

        if (in_array($po->status, ['received', 'cancelled'])) {
            return response()->json(['success' => false, 'message' => 'Cannot cancel this PO.'], 422);
        }

        $po->update(['status' => 'cancelled']);

        return response()->json(['success' => true, 'message' => 'PO cancelled.']);
    }

    // ── DELETE /api/v1/mart/purchase-orders/{id} ─────────────────────────────
    public function destroy(string $id)
    {
        $po = MartPurchaseOrder::findOrFail($id);

        if ($po->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Only draft POs can be deleted.'], 422);
        }

        $po->delete();

        return response()->json(['success' => true, 'message' => 'PO deleted.']);
    }

    // ── PO Number generator ───────────────────────────────────────────────────
    private function generatePoNumber(Branch $branch): string
    {
        $tag = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $branch->name ?? 'BR'), 0, 3));
        do {
            $number = 'MPO-' . $tag . '-' . now()->format('ymd') . '-' . strtoupper(Str::random(4));
        } while (MartPurchaseOrder::where('po_number', $number)->exists());

        return $number;
    }
}
