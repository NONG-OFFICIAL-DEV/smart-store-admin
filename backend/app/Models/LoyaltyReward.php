<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;

#[ScopedBy(TenantScope::class)]
class LoyaltyReward extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'name', 'name_km', 'description', 'description_km', 'image_url',
        'points_cost', 'type', 'discount_value', 'free_product_id',
        'valid_from', 'valid_until', 'is_active',
    ];

    protected $casts = [
        'points_cost' => 'integer',
        'discount_value' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active'   => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function freeProduct()
    {
        return $this->belongsTo(Product::class, 'free_product_id');
    }

    // No rows = available at every branch.
    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'loyalty_reward_branches');
    }

    public function isAvailableAt(?string $branchId): bool
    {
        if (! $branchId) {
            return true;
        }

        $ids = $this->relationLoaded('branches')
            ? $this->branches->pluck('id')
            : $this->branches()->pluck('branches.id');

        return $ids->isEmpty() || $ids->contains($branchId);
    }

    public function isWithinValidDates(): bool
    {
        $today = now()->toDateString();

        if ($this->valid_from && $today < $this->valid_from->toDateString()) {
            return false;
        }

        return ! ($this->valid_until && $today > $this->valid_until->toDateString());
    }

    public function scopeAvailableNow(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()));
    }
}
