<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

// ─────────────────────────────────────────────────────────────────────────────
// Reservation
// ─────────────────────────────────────────────────────────────────────────────
#[ScopedBy(TenantScope::class)]
class Reservation extends BaseModel
{
    protected $fillable = [
        'branch_id',
        'table_id',
        'customer_id',
        'customer_name',
        'customer_phone',
        'party_size',
        'reserved_at',
        'duration_minutes',
        'status',
        'notes',
    ];

    protected $casts = [
        'reserved_at'      => 'datetime',
        'party_size'       => 'integer',
        'duration_minutes' => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
