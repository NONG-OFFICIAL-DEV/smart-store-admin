<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

// ─────────────────────────────────────────────────────────────────────────────
// Promotion
// ─────────────────────────────────────────────────────────────────────────────
#[ScopedBy(TenantScope::class)]
class Promotion extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'name', 'description', 'type',
        'discount_value', 'min_order_amount', 'max_discount_amount',
        'applies_to', 'applicable_ids', 'start_at', 'end_at',
        'usage_limit', 'per_customer_limit', 'is_active',
    ];

    protected $casts = [
        'discount_value'     => 'decimal:2',
        'min_order_amount'   => 'decimal:2',
        'max_discount_amount'=> 'decimal:2',
        'applicable_ids'     => 'array',
        'start_at'           => 'datetime',
        'end_at'             => 'datetime',
        'usage_limit'        => 'integer',
        'usage_count'        => 'integer',
        'per_customer_limit' => 'integer',
        'is_active'          => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function coupons()
    {
        return $this->hasMany(Coupon::class);
    }

    // ─── Calculate discount amount for an order ───────────────────────────────
    public function calculateDiscount(float $subtotal): float
    {
        if ($this->min_order_amount && $subtotal < $this->min_order_amount) return 0;

        $discount = match ($this->type) {
            // Percentage discounts are bounded to 100% even if discount_value was
            // ever stored above that (no validation currently enforces the bound).
            'percentage'   => $subtotal * (min((float) $this->discount_value, 100) / 100),
            'fixed_amount' => (float) $this->discount_value,
            default        => 0,
        };

        if ($this->max_discount_amount) {
            $discount = min($discount, (float) $this->max_discount_amount);
        }

        // Never discount more than the order subtotal itself — otherwise the
        // order total (subtotal - discount + tax + service charge) can go negative.
        $discount = min($discount, $subtotal);

        return round(max($discount, 0), 2);
    }

    public function isActive(): bool
    {
        if (!$this->is_active) return false;
        if ($this->start_at && now()->lt($this->start_at)) return false;
        if ($this->end_at && now()->gt($this->end_at)) return false;
        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) return false;
        return true;
    }
}
