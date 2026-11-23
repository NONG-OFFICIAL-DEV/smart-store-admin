<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

#[ScopedBy(TenantScope::class)]
class Supplier extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'name', 'contact_person', 'phone',
        'email', 'address', 'is_active',
    ];

    // $timestamps = false (no updated_at column) also disables Eloquent's
    // automatic created_at => Carbon casting, which normally only kicks in
    // when timestamps are enabled — cast it explicitly instead, or
    // SupplierResource's ->toIso8601String() call fails on a raw string.
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

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
