<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

#[ScopedBy(TenantScope::class)]
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
