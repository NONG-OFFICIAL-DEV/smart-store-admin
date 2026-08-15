<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService extends BaseService
{
    public function __construct(
        PurchaseOrderRepositoryInterface $repository,
        private InventoryStockService $inventoryStock,
    ) {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function create(array $data, string $tenantId): PurchaseOrder
    {
        $order = $this->repository->create([
            'tenant_id' => $tenantId,
            'branch_id' => $data['branch_id'],
            'supplier_id' => $data['supplier_id'],
            'expected_delivery' => $data['expected_delivery'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by_staff_id' => $data['created_by_staff_id'] ?? null,
            'po_number' => $this->generatePoNumber(),
            'status' => 'draft',
        ]);

        $total = 0;
        foreach ($data['items'] as $item) {
            $line = PurchaseOrderItem::create([
                'purchase_order_id' => $order->id,
                'ingredient_id' => $item['ingredient_id'],
                'quantity_ordered' => $item['quantity_ordered'],
                'quantity_received' => 0,
                'unit_price' => $item['unit_price'],
                'total_price' => $item['quantity_ordered'] * $item['unit_price'],
            ]);
            $total += $line->total_price;
        }
        $order->update(['total_amount' => $total]);

        return $order->fresh(['supplier:id,name', 'branch:id,name', 'items']);
    }

    /**
     * Header fields only — line items are immutable after creation
     * (matches the pre-existing behavior: the old PurchaseOrder::store()
     * only ever processed `items` when creating, never on update). If you
     * change this, note PurchaseOrderDialog.vue's edit form currently lets
     * a user edit item rows and submits them anyway — they're silently
     * discarded server-side today, a pre-existing UI/backend mismatch,
     * not introduced by this migration.
     */
    public function update(PurchaseOrder $order, array $data, string $tenantId): PurchaseOrder
    {
        $order->update([
            'tenant_id' => $tenantId,
            'branch_id' => $data['branch_id'],
            'supplier_id' => $data['supplier_id'],
            'expected_delivery' => $data['expected_delivery'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by_staff_id' => $data['created_by_staff_id'] ?? null,
        ]);

        return $order->fresh(['supplier:id,name', 'branch:id,name', 'items']);
    }

    /**
     * draft -> submitted. Routes referenced this action (and confirm())
     * but neither existed anywhere — meaning a PO could never leave
     * 'draft', which also meant `receive` was unreachable from the UI
     * (PurchaseManagement.vue only shows the receive button for
     * confirmed/partially_received status).
     */
    public function submit(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only a draft PO can be submitted (current status: {$order->status}).",
            ]);
        }

        $order->update(['status' => 'submitted']);

        return $order;
    }

    // submitted -> confirmed.
    public function confirm(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status !== 'submitted') {
            throw ValidationException::withMessages([
                'status' => "Only a submitted PO can be confirmed (current status: {$order->status}).",
            ]);
        }

        $order->update(['status' => 'confirmed']);

        return $order;
    }

    /**
     * Records delivery of ordered items, clamped to what's actually still
     * outstanding per line, and recalculates the PO's status. The routed
     * controller action previously did `$ingredient->increment(
     * 'stock_quantity', ...)` — ingredients.stock_quantity does not exist
     * as a column at all, so every "receive" click fatally errored. The
     * correct approach (InventoryStockService::adjust() — same
     * stock+ledger+low-stock-notification path used everywhere else
     * ingredient stock changes) already existed on PurchaseOrder::receive()
     * but that method had zero callers; the routed action never used it.
     *
     * Requires confirmed/partially_received status — matches
     * PurchaseManagement.vue's own canReceive() gate, which already only
     * shows the button in that state (previously moot: nothing could ever
     * put a PO in 'confirmed' status until submit()/confirm() above
     * existed, so this distinction never actually mattered in practice).
     */
    public function receive(PurchaseOrder $order, array $items, ?string $staffId = null): PurchaseOrder
    {
        if (! in_array($order->status, ['confirmed', 'partially_received'])) {
            throw ValidationException::withMessages([
                'status' => "PO must be confirmed before it can be received (current status: {$order->status}).",
            ]);
        }

        $order->load('items');

        foreach ($items as $recv) {
            $line = $order->items->firstWhere('id', $recv['id']);
            if (! $line) {
                continue;
            }

            $qtyToReceive = min($recv['quantity_received'], $line->quantity_ordered - $line->quantity_received);
            if ($qtyToReceive <= 0) {
                continue;
            }

            $line->update([
                'quantity_received' => $line->quantity_received + $qtyToReceive,
                'received_at' => now(),
            ]);

            $this->inventoryStock->adjust(
                $order->branch_id,
                $line->ingredient_id,
                $qtyToReceive,
                'purchase',
                $staffId,
                "PO# {$order->po_number}"
            );
        }

        $order->refresh()->load('items');
        $allReceived = $order->items->every(fn($i) => $i->quantity_received >= $i->quantity_ordered);
        $anyReceived = $order->items->contains(fn($i) => $i->quantity_received > 0);
        $order->update(['status' => $allReceived ? 'received' : ($anyReceived ? 'partially_received' : $order->status)]);

        return $order->fresh(['supplier:id,name', 'branch:id,name', 'items']);
    }

    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        if (in_array($order->status, ['received', 'cancelled'])) {
            throw ValidationException::withMessages([
                'status' => "Cannot cancel a PO that is already {$order->status}.",
            ]);
        }

        $order->update(['status' => 'cancelled']);

        return $order;
    }

    public function delete(PurchaseOrder $order): void
    {
        if ($order->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft POs can be deleted.',
            ]);
        }

        $this->repository->delete($order);
    }

    /**
     * po_number is unique across the WHOLE table, not per tenant — but
     * PurchaseOrder is TenantScope-scoped, so the original
     * `PurchaseOrder::where(...)` here only ever saw the current tenant's
     * own rows. Two different tenants each creating their first PO in the
     * same month would both compute "0001" and the second insert would
     * fail on the unique constraint. withoutGlobalScopes() so this check
     * actually matches what the database enforces.
     */
    private function generatePoNumber(): string
    {
        $prefix = 'PO-'.now()->format('Ym').'-';
        $last = PurchaseOrder::withoutGlobalScopes()
            ->where('po_number', 'like', $prefix.'%')
            ->orderByDesc('po_number')
            ->value('po_number');
        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
