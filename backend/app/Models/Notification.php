<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use App\Models\Scopes\TenantScope;

// ══════════════════════════════════════════════════════════════════════════════
// Notification
// ══════════════════════════════════════════════════════════════════════════════

#[ScopedBy(TenantScope::class)]
class Notification extends BaseModel
{
    protected $table  = 'notifications';
    const UPDATED_AT  = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'role_id',
        'branch_id',
        'type',
        'title',
        'body',
        'data',
        // NotificationService::markRead()/markAllRead() rely on this —
        // without it, `$model->update(['read_at' => now()])` (an
        // instance-level mass-assignment call) silently no-ops, so every
        // "mark as read" click looked like it worked but never persisted.
        'read_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function role()
    {
        return $this->belongsTo(Role::class);
    }
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

}
