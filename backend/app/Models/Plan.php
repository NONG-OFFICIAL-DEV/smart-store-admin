<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasUuids;

    protected $table = 'plans';

    protected $fillable = [
        'name',
        'code',
        'price_usd',
        'price_khr',
        'seats',
        'storage_gb',
        'api_limit',
        'trial_days',
        'features',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price_usd' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */
    public function billingCycles(): HasMany
    {
        return $this->hasMany(PlanBillingCycle::class)->orderBy('months');
    }

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class)->orderBy('sort_order');
    }

    public function subscriptions()
    {
        return $this->hasMany(TenantSubscription::class);
    }
}
