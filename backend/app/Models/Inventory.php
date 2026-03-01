<?php

namespace App\Models;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// ══════════════════════════════════════════════════════════════════════════════
// Supplier
// ══════════════════════════════════════════════════════════════════════════════

class Supplier extends BaseModel
{
    protected $table  = 'suppliers';
    const UPDATED_AT  = null;

    protected $fillable = [
        'tenant_id', 'name', 'contact_person', 'phone', 'email', 'address', 'payment_terms', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function tenant()       { return $this->belongsTo(Tenant::class); }
    public function ingredients()  { return $this->hasMany(Ingredient::class, 'preferred_supplier_id'); }
    public function purchaseOrders() { return $this->hasMany(PurchaseOrder::class); }

    public static function store(Request $request, $id = null)
    {
        $data = $request->only(['tenant_id', 'name', 'contact_person', 'phone', 'email', 'address', 'payment_terms', 'is_active']);
        if ($id) {
            $record = self::find($id);
            if (!$record) return response()->json(['error' => 'Supplier not found'], 404);
            $record->update($data);
        } else {
            $record = self::create($data);
        }
        return response()->json(['success' => true, 'data' => $record->fresh()], $id ? 200 : 201);
    }
}


// ══════════════════════════════════════════════════════════════════════════════
// Ingredient
// ══════════════════════════════════════════════════════════════════════════════

class Ingredient extends BaseModel
{
    protected $table  = 'ingredients';
    const UPDATED_AT  = null;

    protected $fillable = [
        'tenant_id', 'name', 'category', 'unit', 'unit_cost',
        'reorder_point', 'reorder_quantity', 'preferred_supplier_id', 'barcode', 'is_active',
    ];

    protected $casts = [
        'unit_cost'        => 'decimal:4',
        'reorder_point'    => 'decimal:3',
        'reorder_quantity' => 'decimal:3',
        'is_active'        => 'boolean',
    ];

    public function tenant()           { return $this->belongsTo(Tenant::class); }
    public function preferredSupplier(){ return $this->belongsTo(Supplier::class, 'preferred_supplier_id'); }
    public function stockRecords()     { return $this->hasMany(InventoryStock::class); }
    public function transactions()     { return $this->hasMany(InventoryTransaction::class); }
    public function recipes()          { return $this->hasMany(ProductRecipe::class); }

    public function getStockForBranch(string $branchId): ?InventoryStock
    {
        return $this->stockRecords()->where('branch_id', $branchId)->first();
    }

    public function isLowStock(string $branchId): bool
    {
        $stock = $this->getStockForBranch($branchId);
        if (!$stock || !$this->reorder_point) return false;
        return $stock->quantity_on_hand <= $this->reorder_point;
    }

    public static function store(Request $request, $id = null)
    {
        $data = $request->only([
            'tenant_id', 'name', 'category', 'unit', 'unit_cost',
            'reorder_point', 'reorder_quantity', 'preferred_supplier_id', 'barcode', 'is_active',
        ]);
        if ($id) {
            $record = self::find($id);
            if (!$record) return response()->json(['error' => 'Ingredient not found'], 404);
            $record->update($data);
        } else {
            $record = self::create($data);
        }
        return response()->json(['success' => true, 'data' => $record->fresh()], $id ? 200 : 201);
    }
}


// ══════════════════════════════════════════════════════════════════════════════
// InventoryStock
// ══════════════════════════════════════════════════════════════════════════════

class InventoryStock extends BaseModel
{
    protected $table      = 'inventory_stock';
    public    $timestamps = false;

    protected $fillable = [
        'branch_id', 'ingredient_id', 'quantity_on_hand', 'quantity_reserved', 'last_counted_at',
    ];

    protected $casts = [
        'quantity_on_hand'  => 'decimal:4',
        'quantity_reserved' => 'decimal:4',
        'last_counted_at'   => 'datetime',
    ];

    public function branch()     { return $this->belongsTo(Branch::class); }
    public function ingredient() { return $this->belongsTo(Ingredient::class); }

    /**
     * Adjust stock and create a ledger transaction.
     */
    public function adjust(float $quantity, string $type, array $meta = []): InventoryTransaction
    {
        $this->increment('quantity_on_hand', $quantity);

        return InventoryTransaction::create(array_merge([
            'branch_id'        => $this->branch_id,
            'ingredient_id'    => $this->ingredient_id,
            'transaction_type' => $type,
            'quantity'         => $quantity,
        ], $meta));
    }

    public static function store(Request $request, $id = null)
    {
        $data = $request->only(['branch_id', 'ingredient_id', 'quantity_on_hand', 'quantity_reserved', 'last_counted_at']);
        if ($id) {
            $record = self::find($id);
            if (!$record) return response()->json(['error' => 'Stock record not found'], 404);
            $record->update($data);
        } else {
            $record = self::updateOrCreate(
                ['branch_id' => $data['branch_id'], 'ingredient_id' => $data['ingredient_id']],
                $data
            );
        }
        return response()->json(['success' => true, 'data' => $record->fresh()], 201);
    }
}


// ══════════════════════════════════════════════════════════════════════════════
// InventoryTransaction
// ══════════════════════════════════════════════════════════════════════════════

class InventoryTransaction extends BaseModel
{
    protected $table      = 'inventory_transactions';
    public    $timestamps = false;

    protected $fillable = [
        'branch_id', 'ingredient_id', 'transaction_type', 'quantity',
        'unit_cost', 'reference_type', 'reference_id', 'notes', 'staff_id',
    ];

    protected $casts = [
        'quantity'  => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'created_at'=> 'datetime',
    ];

    public function branch()     { return $this->belongsTo(Branch::class); }
    public function ingredient() { return $this->belongsTo(Ingredient::class); }
    public function staff()      { return $this->belongsTo(Staff::class); }

    public static function store(Request $request, $id = null)
    {
        return DB::transaction(function () use ($request, $id) {
            $data = $request->only([
                'branch_id', 'ingredient_id', 'transaction_type', 'quantity',
                'unit_cost', 'reference_type', 'reference_id', 'notes', 'staff_id',
            ]);

            if ($id) {
                return response()->json(['error' => 'Inventory transactions are immutable'], 403);
            }

            $record = self::create($data);

            // Update stock level
            InventoryStock::updateOrCreate(
                ['branch_id' => $data['branch_id'], 'ingredient_id' => $data['ingredient_id']],
                ['quantity_on_hand' => DB::raw("quantity_on_hand + ({$data['quantity']})")]
            );

            return response()->json(['success' => true, 'data' => $record->fresh()], 201);
        });
    }
}


// ══════════════════════════════════════════════════════════════════════════════
// ProductRecipe
// ══════════════════════════════════════════════════════════════════════════════

class ProductRecipe extends BaseModel
{
    protected $table      = 'product_recipes';
    public    $timestamps = false;

    protected $fillable = [
        'product_id', 'variant_id', 'ingredient_id', 'quantity', 'unit', 'notes',
    ];

    protected $casts = ['quantity' => 'decimal:4'];

    public function product()    { return $this->belongsTo(Product::class); }
    public function variant()    { return $this->belongsTo(ProductVariant::class); }
    public function ingredient() { return $this->belongsTo(Ingredient::class); }

    public static function store(Request $request, $id = null)
    {
        $data = $request->only(['product_id', 'variant_id', 'ingredient_id', 'quantity', 'unit', 'notes']);
        if ($id) {
            $record = self::find($id);
            if (!$record) return response()->json(['error' => 'Recipe line not found'], 404);
            $record->update($data);
        } else {
            $record = self::create($data);
        }
        return response()->json(['success' => true, 'data' => $record->fresh()->load('ingredient')], $id ? 200 : 201);
    }
}


// ══════════════════════════════════════════════════════════════════════════════
// PurchaseOrder
// ══════════════════════════════════════════════════════════════════════════════

class PurchaseOrder extends BaseModel
{
    protected $table = 'purchase_orders';

    protected $fillable = [
        'tenant_id', 'branch_id', 'supplier_id', 'po_number', 'status',
        'expected_delivery', 'total_amount', 'notes', 'created_by_staff_id',
    ];

    protected $casts = [
        'expected_delivery' => 'date',
        'total_amount'      => 'decimal:2',
    ];

    public function tenant()   { return $this->belongsTo(Tenant::class); }
    public function branch()   { return $this->belongsTo(Branch::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function items()    { return $this->hasMany(PurchaseOrderItem::class); }
    public function createdBy(){ return $this->belongsTo(Staff::class, 'created_by_staff_id'); }

    public static function generatePoNumber(): string
    {
        $prefix = 'PO-' . now()->format('Ym') . '-';
        $last   = self::where('po_number', 'like', "{$prefix}%")->orderByDesc('po_number')->value('po_number');
        $seq    = $last ? ((int) substr($last, -4)) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public static function store(Request $request, $id = null)
    {
        return DB::transaction(function () use ($request, $id) {
            $data = $request->only([
                'tenant_id', 'branch_id', 'supplier_id', 'status',
                'expected_delivery', 'notes', 'created_by_staff_id',
            ]);

            if ($id) {
                $record = self::find($id);
                if (!$record) return response()->json(['error' => 'Purchase order not found'], 404);
                $record->update($data);
            } else {
                $data['po_number'] = self::generatePoNumber();
                $data['status']    = 'draft';
                $record = self::create($data);
            }

            // Save PO line items if provided
            if ($request->has('items')) {
                if ($id) $record->items()->delete();
                $total = 0;
                foreach ($request->input('items', []) as $item) {
                    $lineTotal = $item['quantity_ordered'] * $item['unit_price'];
                    $total    += $lineTotal;
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $record->id,
                        'ingredient_id'     => $item['ingredient_id'],
                        'quantity_ordered'  => $item['quantity_ordered'],
                        'quantity_received' => 0,
                        'unit_price'        => $item['unit_price'],
                        'total_price'       => $lineTotal,
                    ]);
                }
                $record->update(['total_amount' => $total]);
            }

            return response()->json([
                'success' => true,
                'data'    => $record->fresh()->load('items.ingredient', 'supplier'),
            ], $id ? 200 : 201);
        });
    }
}


// ══════════════════════════════════════════════════════════════════════════════
// PurchaseOrderItem
// ══════════════════════════════════════════════════════════════════════════════

class PurchaseOrderItem extends BaseModel
{
    protected $table      = 'purchase_order_items';
    public    $timestamps = false;

    protected $fillable = [
        'purchase_order_id', 'ingredient_id', 'quantity_ordered', 'quantity_received',
        'unit_price', 'total_price', 'received_at',
    ];

    protected $casts = [
        'quantity_ordered'  => 'decimal:3',
        'quantity_received' => 'decimal:3',
        'unit_price'        => 'decimal:4',
        'total_price'       => 'decimal:2',
        'received_at'       => 'datetime',
    ];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function ingredient()    { return $this->belongsTo(Ingredient::class); }
}
