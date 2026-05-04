<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TerminalTrust extends Model
{
    use HasUuids; // ← this auto-generates UUID for id

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'actor_type',
        'actor_id',
        'terminal_id',
        'device_name',
        'is_revoked',
        'trusted_at',
        'expires_at',
        'last_used_at',
    ];

    protected $casts = [
        'is_revoked'   => 'boolean',
        'trusted_at'   => 'datetime',
        'expires_at'   => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function actor()
    {
        return $this->morphTo();
    }
}
