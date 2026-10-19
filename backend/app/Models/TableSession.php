<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

// ─────────────────────────────────────────────────────────────────────────────
// TableSession — one running tab per table. Every dine-in order placed while
// a session is open joins it (see TableSessionService::getOrCreateOpenSession());
// the bill shown on the Table modal is the merged total of every non-
// cancelled/refunded order in the session, not any single order on its own.
// ─────────────────────────────────────────────────────────────────────────────
#[ScopedBy(TenantScope::class)]
class TableSession extends BaseModel
{
    protected $fillable = [
        'table_id',
        'branch_id',
        'status',
        'opened_at',
        'closed_at',
        'opened_by',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    const STATUS_OPEN   = 'open';
    const STATUS_CLOSED = 'closed';

    // ─── Relationships ────────────────────────────────────────────────────────
    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function openedBy()
    {
        return $this->belongsTo(Staff::class, 'opened_by');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
