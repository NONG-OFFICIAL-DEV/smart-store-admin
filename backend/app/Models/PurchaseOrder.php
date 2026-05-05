<?php

namespace App\Models;

use Illuminate\Http\Request;

// ─────────────────────────────────────────────────────────────────────────────
// PurchaseOrder
// ─────────────────────────────────────────────────────────────────────────────
class PurchaseOrder extends BaseModel
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'supplier_id',
        'po_number',
        'status',
        'expected_delivery',
        'total_amount',
        'notes',
        'created_by_staff_id',
    ];

    protected $casts = [
        'expected_delivery' => 'date',
        'total_amount'      => 'decimal:2',
    ];

    public static function store(array|Request $request, ?string $id = null, ?string $tenantId = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'tenant_id',
                'branch_id',
                'supplier_id',
                'expected_delivery',
                'notes',
                'created_by_staff_id',
            ])
            : $request;


        // ── Inject resolved tenant_id ──────────────────────────────────────────
        if ($tenantId) {
            $data['tenant_id'] = $tenantId;
        }

        if (!$id) {
            $data['po_number'] = static::generatePoNumber();
            $data['status']    = 'draft';
        }

        $result = parent::store($data, $id);

        // Save line items if provided
        if ($request instanceof Request && $request->has('items') && !$id) {
            $po    = static::latest()->first();
            $total = 0;
            foreach ($request->items as $item) {
                $line = PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'ingredient_id'     => $item['ingredient_id'],
                    'quantity_ordered'  => $item['quantity_ordered'],
                    'quantity_received' => 0,
                    'unit_price'        => $item['unit_price'],
                    'total_price'       => $item['quantity_ordered'] * $item['unit_price'],
                ]);
                $total += $line->total_price;
            }
            $po->update(['total_amount' => $total]);
        }

        return $result;
    }

    // ─── Receive stock ────────────────────────────────────────────────────────
    public static function receive(string $id, array $receivedItems, ?string $staffId = null)
    {
        $po = static::find($id);
        if (!$po) return response()->json(['error' => 'PO not found'], 404);

        foreach ($receivedItems as $item) {
            $line = PurchaseOrderItem::find($item['purchase_order_item_id']);
            if (!$line) continue;

            $line->update([
                'quantity_received' => $item['quantity_received'],
                'received_at'       => now(),
            ]);

            // Add stock
            InventoryStock::adjust(
                $po->branch_id,
                $line->ingredient_id,
                $item['quantity_received'],
                'purchase',
                $staffId,
                "PO# {$po->po_number}"
            );
        }

        $allReceived = $po->items()->whereColumn('quantity_received', '<', 'quantity_ordered')->doesntExist();
        $po->update(['status' => $allReceived ? 'received' : 'partially_received']);

        return response()->json(['success' => true, 'data' => $po->load('items')], 200);
    }

    public static function generatePoNumber(): string
    {
        $prefix = 'PO-' . now()->format('Ym') . '-';
        $last   = static::where('po_number', 'like', $prefix . '%')
            ->orderByDesc('po_number')
            ->value('po_number');
        $next = $last ? ((int) substr($last, -4)) + 1 : 1;
        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
    public function createdBy()
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// PurchaseOrderItem
// ─────────────────────────────────────────────────────────────────────────────
class PurchaseOrderItem extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'purchase_order_id',
        'ingredient_id',
        'quantity_ordered',
        'quantity_received',
        'unit_price',
        'total_price',
        'received_at',
    ];

    protected $casts = [
        'quantity_ordered'  => 'decimal:3',
        'quantity_received' => 'decimal:3',
        'unit_price'        => 'decimal:4',
        'total_price'       => 'decimal:2',
        'received_at'       => 'datetime',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'purchase_order_id',
                'ingredient_id',
                'quantity_ordered',
                'unit_price',
            ])
            : $request;

        if (!empty($data['quantity_ordered']) && !empty($data['unit_price'])) {
            $data['total_price']       = $data['quantity_ordered'] * $data['unit_price'];
            $data['quantity_received'] = 0;
        }

        return parent::store($data, $id);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
