<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

// This table has neither tenant_id nor branch_id of its own — tenant-owned
// only indirectly via cash_drawer_id -> cash_drawers.branch_id, handled by
// TenantScope's 'cash_transactions' special case (same shape as
// refunds -> payments). The #[ScopedBy] attribute is still required to
// activate the scope at all — without it, TenantScope::apply() never runs
// for this model regardless of what the scope class itself can handle.
#[ScopedBy(TenantScope::class)]
class CashTransaction extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'cash_drawer_id', 'staff_id', 'type', 'amount', 'reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function cashDrawer()
    {
        return $this->belongsTo(CashDrawer::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
