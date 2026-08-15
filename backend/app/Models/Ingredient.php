<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

// ══════════════════════════════════════════════════════════════════════════════
// Ingredient
// ══════════════════════════════════════════════════════════════════════════════

#[ScopedBy(TenantScope::class)]
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
}
