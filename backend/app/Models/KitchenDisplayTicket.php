<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

#[ScopedBy(TenantScope::class)]
class KitchenDisplayTicket extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'order_id', 'branch_id', 'station', 'status', 'priority',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'priority'     => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function getPrepTimeMinutesAttribute(): ?int
    {
        if (!$this->started_at || !$this->completed_at) return null;
        return $this->started_at->diffInMinutes($this->completed_at);
    }
}
