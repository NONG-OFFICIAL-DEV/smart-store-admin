<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

// ─────────────────────────────────────────────────────────────────────────────
// Payment
// ─────────────────────────────────────────────────────────────────────────────
#[ScopedBy(TenantScope::class)]
class Payment extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'order_id', 'branch_id', 'staff_id', 'payment_method',
        'amount', 'change_given', 'currency', 'exchange_rate',
        'status', 'gateway', 'gateway_transaction_id',
        'gateway_response', 'receipt_number', 'paid_at',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'change_given'     => 'decimal:2',
        'exchange_rate'    => 'decimal:6',
        'gateway_response' => 'array',
        'paid_at'          => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }
}
