<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

#[ScopedBy(TenantScope::class)]
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
