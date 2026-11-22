<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;
use Illuminate\Support\Facades\DB;

#[ScopedBy(TenantScope::class)]
class Customer extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'first_name', 'last_name', 'email', 'phone',
        'avatar_url', 'notes', 'marketing_opt_in', 'source', 'is_active',
        'telegram_chat_id', 'notify_telegram', 'date_of_birth',
    ];

    protected $casts = [
        'marketing_opt_in' => 'boolean',
        'is_active'        => 'boolean',
        'total_spent'      => 'decimal:2',
        'total_orders'     => 'integer',
        'loyalty_points'   => 'integer',
        'notify_telegram'  => 'boolean',
        'date_of_birth'    => 'date',
    ];

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

    public function loyaltyRedemptions()
    {
        return $this->hasMany(LoyaltyRedemption::class);
    }

    // ─── Points helpers ───────────────────────────────────────────────────────
    // Locks the row for the whole read-then-write so two concurrent
    // redemption requests (or a redemption racing a refund reversal) can't
    // both pass a stale balance check — the loyalty program's whole premise
    // is "the balance is always correct," so this can't be best-effort.
    public function addPoints(int $points, ?string $orderId = null, string $type = 'earn'): void
    {
        DB::transaction(function () use ($points, $orderId, $type) {
            $customer = static::whereKey($this->id)->lockForUpdate()->first();
            $newBalance = $customer->loyalty_points + $points;

            LoyaltyTransaction::create([
                'customer_id'   => $customer->id,
                'order_id'      => $orderId,
                'type'          => $type,
                'points'        => $points,
                'balance_after' => $newBalance,
                'description'   => "Points {$type}",
            ]);

            // loyalty_points isn't fillable (only this ledgered path may move
            // it), so increment()/decrement() — not update() — which bypass
            // mass-assignment guarding by design.
            $customer->increment('loyalty_points', $points);
        });

        $this->refresh();
    }

    public function redeemPoints(int $points, ?string $orderId = null): bool
    {
        $redeemed = DB::transaction(function () use ($points, $orderId) {
            $customer = static::whereKey($this->id)->lockForUpdate()->first();

            if ($customer->loyalty_points < $points) {
                return false;
            }

            $newBalance = $customer->loyalty_points - $points;

            LoyaltyTransaction::create([
                'customer_id'   => $customer->id,
                'order_id'      => $orderId,
                'type'          => 'redeem',
                'points'        => -$points,
                'balance_after' => $newBalance,
                'description'   => 'Points redeemed',
            ]);

            $customer->decrement('loyalty_points', $points);

            return true;
        });

        $this->refresh();

        return $redeemed;
    }

    /**
     * A refunded order's earn is reversed unconditionally, even if the
     * customer already spent those stamps on a reward — unlike
     * redeemPoints(), this can't refuse for "insufficient points" (the
     * order genuinely didn't happen, so the balance must correct even if
     * that takes it negative; it works itself off on the next earn).
     */
    public function reversePoints(int $points, ?string $orderId = null): void
    {
        DB::transaction(function () use ($points, $orderId) {
            $customer = static::whereKey($this->id)->lockForUpdate()->first();
            $newBalance = $customer->loyalty_points - $points;

            LoyaltyTransaction::create([
                'customer_id'   => $customer->id,
                'order_id'      => $orderId,
                'type'          => 'adjust',
                'points'        => -$points,
                'balance_after' => $newBalance,
                'description'   => 'Points reversed (order refunded)',
            ]);

            $customer->decrement('loyalty_points', $points);
        });

        $this->refresh();
    }

    // ─── Accessors ────────────────────────────────────────────────────────────
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
