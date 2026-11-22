<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

#[ScopedBy(TenantScope::class)]
class LoyaltySetting extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'enabled', 'earning_type', 'earning_rate',
        'notify_stamps_earned', 'notify_reward_ready', 'notify_reward_expiring', 'notify_birthday',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'earning_rate' => 'decimal:2',
        'notify_stamps_earned' => 'boolean',
        'notify_reward_ready' => 'boolean',
        'notify_reward_expiring' => 'boolean',
        'notify_birthday' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function forTenant(string $tenantId): self
    {
        // enabled/earning_type/earning_rate are DB-level defaults, not
        // mass-assigned here — firstOrCreate()'s in-memory instance won't
        // reflect them on a fresh insert without this refresh().
        return static::firstOrCreate(['tenant_id' => $tenantId])->refresh();
    }

    // How many stamps a just-completed $orderTotal order earns.
    public function stampsFor(float $orderTotal): int
    {
        if (! $this->enabled) {
            return 0;
        }

        return match ($this->earning_type) {
            'per_currency' => $this->earning_rate > 0 ? (int) floor($orderTotal / $this->earning_rate) : 0,
            default => (int) $this->earning_rate,
        };
    }
}
