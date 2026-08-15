<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy(TenantScope::class)]
class SubscriptionPlanHistory extends Model
{
    use HasUuids;

    protected $table = 'subscription_plan_history';

    protected $fillable = [
        'tenant_id',
        'billing_cycle_id',
        'from_plan_id',
        'to_plan_id',
        'changed_by',
        'reason',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function billingCycle(): BelongsTo
    {
        return $this->belongsTo(PlanBillingCycle::class, 'billing_cycle_id');
    }

    public function fromPlan()
    {
        return $this->belongsTo(
            Plan::class,
            'from_plan_id'
        );
    }

    public function toPlan()
    {
        return $this->belongsTo(
            Plan::class,
            'to_plan_id'
        );
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
