<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

#[ScopedBy(TenantScope::class)]
class LoyaltyTransaction extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'customer_id', 'branch_id', 'order_id',
        'type', 'points', 'balance_after', 'description', 'expires_at',
    ];

    protected $casts = [
        'points'        => 'integer',
        'balance_after' => 'integer',
        'expires_at'    => 'datetime',
        'created_at'    => 'datetime',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function branch()   { return $this->belongsTo(Branch::class); }
    public function order()    { return $this->belongsTo(Order::class); }

    // A 'redeem' row's own order_id/branch_id are always null (see
    // LoyaltyRedemptionService::requestRedemption() — points are deducted
    // before any order exists) — the branch/order/reward context for a
    // redemption instead lives on the LoyaltyRedemption row it created,
    // populated later at confirm() time.
    public function redemption() { return $this->hasOne(LoyaltyRedemption::class); }
}
