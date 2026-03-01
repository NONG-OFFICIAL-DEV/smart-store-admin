<?php

namespace App\Models;

use Illuminate\Http\Request;

// ─────────────────────────────────────────────────────────────────────────────
// Supplier
// ─────────────────────────────────────────────────────────────────────────────
class Supplier extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'name', 'contact_person', 'phone',
        'email', 'address', 'payment_terms', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'tenant_id', 'name', 'contact_person', 'phone',
                'email', 'address', 'payment_terms', 'is_active',
            ])
            : $request;

        return parent::store($data, $id);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function ingredients()
    {
        return $this->hasMany(Ingredient::class, 'preferred_supplier_id');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Ingredient
// ─────────────────────────────────────────────────────────────────────────────
class Ingredient extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'name', 'category', 'unit',
        'unit_cost', 'reorder_point', 'reorder_quantity',
        'preferred_supplier_id', 'barcode', 'is_active',
    ];

    protected $casts = [
        'unit_cost'        => 'decimal:4',
        'reorder_point'    => 'decimal:3',
        'reorder_quantity' => 'decimal:3',
        'is_active'        => 'boolean',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'tenant_id', 'name', 'category', 'unit',
                'unit_cost', 'reorder_point', 'reorder_quantity',
                'preferred_supplier_id', 'barcode', 'is_active',
            ])
            : $request;

        return parent::store($data, $id);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function preferredSupplier()
    {
        return $this->belongsTo(Supplier::class, 'preferred_supplier_id');
    }

    public function stock()
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function transactions()
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function stockAtBranch(string $branchId): ?InventoryStock
    {
        return $this->stock()->where('branch_id', $branchId)->first();
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// InventoryStock
// ─────────────────────────────────────────────────────────────────────────────
class InventoryStock extends BaseModel
{
    protected $table = 'inventory_stock';

    public $timestamps = false;

    protected $fillable = [
        'branch_id', 'ingredient_id',
        'quantity_on_hand', 'quantity_reserved', 'last_counted_at',
    ];

    protected $casts = [
        'quantity_on_hand'  => 'decimal:4',
        'quantity_reserved' => 'decimal:4',
        'last_counted_at'   => 'datetime',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'branch_id', 'ingredient_id',
                'quantity_on_hand', 'quantity_reserved',
            ])
            : $request;

        return parent::store($data, $id);
    }

    // ─── Adjust stock ─────────────────────────────────────────────────────────
    public static function adjust(
        string $branchId,
        string $ingredientId,
        float $quantity,
        string $type,
        ?string $staffId = null,
        ?string $notes = null
    ) {
        $stock = static::firstOrCreate(
            ['branch_id' => $branchId, 'ingredient_id' => $ingredientId],
            ['quantity_on_hand' => 0, 'quantity_reserved' => 0]
        );

        $stock->increment('quantity_on_hand', $quantity);

        // Record in ledger
        InventoryTransaction::create([
            'branch_id'        => $branchId,
            'ingredient_id'    => $ingredientId,
            'transaction_type' => $type,
            'quantity'         => $quantity,
            'staff_id'         => $staffId,
            'notes'            => $notes,
        ]);

        // Check low stock
        $ingredient = Ingredient::find($ingredientId);
        if ($ingredient?->reorder_point && $stock->quantity_on_hand <= $ingredient->reorder_point) {
            Notification::create([
                'tenant_id' => $ingredient->tenant_id,
                'branch_id' => $branchId,
                'type'      => 'low_stock',
                'title'     => 'Low Stock Alert',
                'body'      => "{$ingredient->name} is running low ({$stock->quantity_on_hand} {$ingredient->unit} remaining).",
                'data'      => json_encode(['ingredient_id' => $ingredientId]),
            ]);
        }

        return response()->json(['success' => true, 'data' => $stock], 200);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function getAvailableQuantityAttribute(): float
    {
        return max(0, (float) $this->quantity_on_hand - (float) $this->quantity_reserved);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// InventoryTransaction
// ─────────────────────────────────────────────────────────────────────────────
class InventoryTransaction extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'branch_id', 'ingredient_id', 'transaction_type',
        'quantity', 'unit_cost', 'reference_type',
        'reference_id', 'notes', 'staff_id',
    ];

    protected $casts = [
        'quantity'   => 'decimal:4',
        'unit_cost'  => 'decimal:4',
        'created_at' => 'datetime',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'branch_id', 'ingredient_id', 'transaction_type',
                'quantity', 'unit_cost', 'reference_type',
                'reference_id', 'notes', 'staff_id',
            ])
            : $request;

        $record = static::create($data);
        return response()->json(['success' => true, 'data' => $record], 201);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// ProductRecipe
// ─────────────────────────────────────────────────────────────────────────────
class ProductRecipe extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'product_id', 'variant_id', 'ingredient_id',
        'quantity', 'unit', 'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'product_id', 'variant_id', 'ingredient_id',
                'quantity', 'unit', 'notes',
            ])
            : $request;

        return parent::store($data, $id);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// PurchaseOrder
// ─────────────────────────────────────────────────────────────────────────────
class PurchaseOrder extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'branch_id', 'supplier_id', 'po_number',
        'status', 'expected_delivery', 'total_amount',
        'notes', 'created_by_staff_id',
    ];

    protected $casts = [
        'expected_delivery' => 'date',
        'total_amount'      => 'decimal:2',
    ];

    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'tenant_id', 'branch_id', 'supplier_id',
                'expected_delivery', 'notes', 'created_by_staff_id',
            ])
            : $request;

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
                    'quantity_ordered'  => $item['quantity'],
                    'quantity_received' => 0,
                    'unit_price'        => $item['unit_price'],
                    'total_price'       => $item['quantity'] * $item['unit_price'],
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

    public function tenant()     { return $this->belongsTo(Tenant::class); }
    public function branch()     { return $this->belongsTo(Branch::class); }
    public function supplier()   { return $this->belongsTo(Supplier::class); }
    public function createdBy()  { return $this->belongsTo(Staff::class, 'created_by_staff_id'); }

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
        'purchase_order_id', 'ingredient_id',
        'quantity_ordered', 'quantity_received',
        'unit_price', 'total_price', 'received_at',
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
                'purchase_order_id', 'ingredient_id',
                'quantity_ordered', 'unit_price',
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
