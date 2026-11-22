<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

#[ScopedBy(TenantScope::class)]
class LoyaltyRedemption extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'customer_id', 'loyalty_reward_id', 'order_id', 'branch_id', 'code', 'status',
        'expires_at', 'redeemed_at', 'redeemed_by_staff_id', 'loyalty_transaction_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'redeemed_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function reward()
    {
        return $this->belongsTo(LoyaltyReward::class, 'loyalty_reward_id');
    }

    public function redeemedByStaff()
    {
        return $this->belongsTo(User::class, 'redeemed_by_staff_id');
    }

    public function loyaltyTransaction()
    {
        return $this->belongsTo(LoyaltyTransaction::class);
    }

    public function isExpired(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isPast();
    }
}
