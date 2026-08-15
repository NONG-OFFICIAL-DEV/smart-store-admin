<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

// ─────────────────────────────────────────────────────────────────────────────
// PurchaseOrder
// ─────────────────────────────────────────────────────────────────────────────
#[ScopedBy(TenantScope::class)]
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
