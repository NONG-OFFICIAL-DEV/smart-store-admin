<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\MartPurchaseOrder;
use App\Models\MartPurchaseOrderItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use App\Repositories\Contracts\MartPurchaseOrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MartPurchaseOrderService extends BaseService
{
    public function __construct(MartPurchaseOrderRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function create(array $data): MartPurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $branch = Branch::findOrFail($data['branch_id']);

            $order = $this->repository->create([
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $data['branch_id'],
                'supplier_id' => $data['supplier_id'],
                'po_number' => $this->generatePoNumber($branch),
                'status' => 'draft',
                'expected_delivery' => $data['expected_delivery'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_staff_id' => auth()->user()->staff?->id,
            ]);

            $order->update(['total_amount' => $this->createItems($order, $data['items'])]);

            return $order->fresh(['supplier:id,name', 'items']);
        });
    }

    public function update(MartPurchaseOrder $order, array $data): MartPurchaseOrder
    {
        if (! in_array($order->status, ['draft', 'submitted'])) {
            throw ValidationException::withMessages([
                'status' => 'Only draft or submitted POs can be edited.',
            ]);
        }

        return DB::transaction(function () use ($data, $order) {
            $order->update(array_intersect_key($data, array_flip(['supplier_id', 'expected_delivery', 'notes', 'status'])));

            if (array_key_exists('items', $data)) {
                $order->items()->delete();
                $order->update(['total_amount' => $this->createItems($order, $data['items'])]);
            }

            return $order->fresh(['items', 'supplier']);
        });
    }

    private function createItems(MartPurchaseOrder $order, array $items): float
    {
        $total = 0;

        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);
            $productUnit = isset($item['product_unit_id']) ? ProductUnit::find($item['product_unit_id']) : null;
            $totalCost = $item['unit_cost'] * $item['quantity_ordered'];
            $total += $totalCost;

            MartPurchaseOrderItem::create([
                'mart_purchase_order_id' => $order->id,
                'product_id' => $product->id,
                'product_unit_id' => $productUnit?->id,
                'product_name' => $product->name,
                'unit_name' => $productUnit?->unit_name ?? $product->unit ?? 'pcs',
                'qty_per_base' => $productUnit?->qty_per_base ?? 1,
                'quantity_ordered' => $item['quantity_ordered'],
                'quantity_received' => 0,
                'unit_cost' => $item['unit_cost'],
                'total_cost' => $totalCost,
            ]);
        }

        return $total;
    }

    public function receive(MartPurchaseOrder $order, array $items, ?string $notes = null): MartPurchaseOrder
    {
        return DB::transaction(function () use ($order, $items, $notes) {
            // Lock the PO row so a concurrent "receive" request for the same
            // PO can't read the same pre-update quantities and double-count.
            $order = MartPurchaseOrder::lockForUpdate()->with('items.product')->findOrFail($order->id);

            if (in_array($order->status, ['received', 'cancelled'])) {
                throw ValidationException::withMessages([
                    'status' => 'This PO is already received or cancelled.',
                ]);
            }

            $staff = auth()->user()->staff;

            foreach ($items as $receivedItem) {
                // Re-fetch each line item under lock — the eager-loaded
                // copy on $order may be stale relative to another
                // transaction that committed between load and lock.
                $lineItem = MartPurchaseOrderItem::where('id', $receivedItem['id'])
                    ->where('mart_purchase_order_id', $order->id)
                    ->lockForUpdate()
                    ->first();
                if (! $lineItem) {
                    continue;
                }

                $qtyReceiving = min((float) $receivedItem['quantity_received'], $lineItem->quantity_ordered - $lineItem->quantity_received);
                if ($qtyReceiving <= 0) {
                    continue;
                }

                $lineItem->increment('quantity_received', $qtyReceiving);
                $lineItem->update(['received_at' => now()]);

                $baseQtyIn = $qtyReceiving * $lineItem->qty_per_base;
                $product = Product::lockForUpdate()->find($lineItem->product_id);
                $qtyBefore = (float) $product->stock_quantity;
                $qtyAfter = $qtyBefore + $baseQtyIn;

                $product->increment('stock_quantity', $baseQtyIn);

                StockMovement::create([
                    'branch_id' => $order->branch_id,
                    'product_id' => $product->id,
                    'movement_type' => 'purchase',
                    'quantity' => $baseQtyIn,
                    'qty_before' => $qtyBefore,
                    'qty_after' => $qtyAfter,
                    'unit_cost' => $lineItem->unit_cost,
                    'reference_type' => 'mart_purchase_order',
                    'reference_id' => $order->id,
                    'notes' => $notes ?? "Received from PO {$order->po_number}",
                    'staff_id' => $staff?->id,
                ]);
            }

            $order->load('items');
            $allReceived = $order->items->every(fn($i) => $i->quantity_received >= $i->quantity_ordered);
            $anyReceived = $order->items->contains(fn($i) => $i->quantity_received > 0);
            $order->status = $allReceived ? 'received' : ($anyReceived ? 'partially_received' : $order->status);
            $order->save();

            return $order->fresh(['items.product', 'supplier']);
        });
    }

    public function cancel(MartPurchaseOrder $order): MartPurchaseOrder
    {
        if (in_array($order->status, ['received', 'cancelled'])) {
            throw ValidationException::withMessages([
                'status' => 'Cannot cancel this PO.',
            ]);
        }

        $order->update(['status' => 'cancelled']);

        return $order;
    }

    public function delete(MartPurchaseOrder $order): void
    {
        if ($order->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft POs can be deleted.',
            ]);
        }

        $this->repository->delete($order);
    }

    private function generatePoNumber(Branch $branch): string
    {
        $tag = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $branch->name ?? 'BR'), 0, 3));
        do {
            $number = 'MPO-'.$tag.'-'.now()->format('ymd').'-'.strtoupper(Str::random(4));
        } while (MartPurchaseOrder::withoutGlobalScopes()->where('po_number', $number)->exists());

        return $number;
    }
}
