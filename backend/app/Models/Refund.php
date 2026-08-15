<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

#[ScopedBy(TenantScope::class)]
class Refund extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'payment_id', 'order_id', 'staff_id',
        'amount', 'reason', 'method', 'status', 'gateway_refund_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
