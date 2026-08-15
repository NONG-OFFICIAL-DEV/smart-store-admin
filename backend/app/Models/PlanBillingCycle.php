<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanBillingCycle extends Model
{
    use HasUuids;

    protected $fillable = [
        'plan_id',
        'label',
        'months',
        'discount_percent',
        'is_active'
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'is_active'        => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
