<?php

namespace App\Models;

use Illuminate\Http\Request;

class Customer extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'first_name', 'last_name', 'email', 'phone',
        'date_of_birth', 'gender', 'avatar_url', 'notes',
        'marketing_opt_in', 'preferred_language', 'source', 'is_active',
    ];

    protected $casts = [
        'date_of_birth'    => 'date',
        'marketing_opt_in' => 'boolean',
        'is_active'        => 'boolean',
        'total_spent'      => 'decimal:2',
        'total_orders'     => 'integer',
        'loyalty_points'   => 'integer',
    ];

    // ─── Store ────────────────────────────────────────────────────────────────
    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'tenant_id', 'first_name', 'last_name', 'email', 'phone',
                'date_of_birth', 'gender', 'avatar_url', 'notes',
                'marketing_opt_in', 'preferred_language', 'source', 'is_active',
            ])
            : $request;

        return parent::store($data, $id);
    }

    // ─── Relationships ────────────────────────────────────────────────────────
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function defaultAddress()
    {
        return $this->hasOne(CustomerAddress::class)->where('is_default', true);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function loyaltyTransactions()
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    // ─── Points helpers ───────────────────────────────────────────────────────
    public function addPoints(int $points, ?string $orderId = null, string $type = 'earn'): void
    {
        $newBalance = $this->loyalty_points + $points;

        LoyaltyTransaction::create([
            'customer_id'   => $this->id,
            'order_id'      => $orderId,
            'type'          => $type,
            'points'        => $points,
            'balance_after' => $newBalance,
            'description'   => "Points {$type}",
        ]);

        $this->increment('loyalty_points', $points);
    }

    public function redeemPoints(int $points, ?string $orderId = null): bool
    {
        if ($this->loyalty_points < $points) return false;

        $newBalance = $this->loyalty_points - $points;

        LoyaltyTransaction::create([
            'customer_id'   => $this->id,
            'order_id'      => $orderId,
            'type'          => 'redeem',
            'points'        => -$points,
            'balance_after' => $newBalance,
            'description'   => 'Points redeemed',
        ]);

        $this->decrement('loyalty_points', $points);
        return true;
    }

    // ─── Accessors ────────────────────────────────────────────────────────────
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
