<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

// ─────────────────────────────────────────────────────────────────────────────
// Coupon
// ─────────────────────────────────────────────────────────────────────────────
#[ScopedBy(TenantScope::class)]
class Coupon extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'promotion_id', 'code', 'usage_limit', 'is_active', 'expires_at',
    ];

    protected $casts = [
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'is_active'   => 'boolean',
        'expires_at'  => 'datetime',
    ];

    // ─── Apply coupon to an order ─────────────────────────────────────────────
    public static function apply(string $code, string $orderId, ?string $customerId = null)
    {
        $coupon = static::where('code', $code)->where('is_active', true)->first();
        if (!$coupon) {
            return response()->json(['error' => 'Invalid coupon code'], 422);
        }
        if ($coupon->expires_at && now()->gt($coupon->expires_at)) {
            return response()->json(['error' => 'Coupon has expired'], 422);
        }
        if ($coupon->usage_limit && $coupon->usage_count >= $coupon->usage_limit) {
            return response()->json(['error' => 'Coupon usage limit reached'], 422);
        }

        $order    = Order::find($orderId);
        $discount = $coupon->promotion->calculateDiscount($order->subtotal);

        CouponUsage::create([
            'coupon_id'        => $coupon->id,
            'order_id'         => $orderId,
            'customer_id'      => $customerId,
            'discount_applied' => $discount,
        ]);

        $coupon->increment('usage_count');
        $coupon->promotion->increment('usage_count');

        $order->update([
            'coupon_code'     => $code,
            'discount_amount' => $discount,
            'total_amount'    => $order->subtotal - $discount + $order->tax_amount + $order->service_charge_amount,
        ]);

        return response()->json(['success' => true, 'discount' => $discount, 'data' => $order->fresh()], 200);
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }
}

