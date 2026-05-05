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
