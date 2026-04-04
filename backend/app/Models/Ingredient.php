<?php

namespace App\Models;
use Illuminate\Http\Request;

// ══════════════════════════════════════════════════════════════════════════════
// Ingredient
// ══════════════════════════════════════════════════════════════════════════════

class Ingredient extends BaseModel
{
    protected $table  = 'ingredients';
    const UPDATED_AT  = null;

    protected $fillable = [
        'tenant_id',
        'name',
        'category',
        'unit',
        'unit_cost',
        'reorder_point',
        'reorder_quantity',
        'preferred_supplier_id',
        'barcode',
        'is_active',
    ];

    protected $casts = [
        'unit_cost'        => 'decimal:4',
        'reorder_point'    => 'decimal:3',
        'reorder_quantity' => 'decimal:3',
        'is_active'        => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    public function preferredSupplier()
    {
        return $this->belongsTo(Supplier::class, 'preferred_supplier_id');
    }
    public function stockRecords()
    {
        return $this->hasMany(InventoryStock::class);
    }
    public function transactions()
    {
        return $this->hasMany(InventoryTransaction::class);
    }
    public function recipes()
    {
        return $this->hasMany(ProductRecipe::class);
    }

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

    public static function store(array|Request $request, ?string $id = null, ?string $tenantId = null)
    {
        $data = $request->only([
            'name',
            'category',
            'unit',
            'unit_cost',
            'reorder_point',
            'reorder_quantity',
            'preferred_supplier_id',
            'barcode',
            'is_active',
        ]);
        // ── Inject resolved tenant_id ──────────────────────────────────────────
        if ($tenantId) {
            $data['tenant_id'] = $tenantId;
        }
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
