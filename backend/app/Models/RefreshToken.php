<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RefreshToken extends Model
{
     use HasUuids;

    protected $fillable = [
        'tenant_id',
        'actor_type',
        'actor_id',
        'terminal_id',
        'token_hash',
        'is_revoked',
        'expires_at',
        'rotated_at',
    ];

    protected $casts = [
        'is_revoked' => 'boolean',
        'expires_at' => 'datetime',
        'rotated_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function actor()
    {
        return $this->morphTo();
    }
}
