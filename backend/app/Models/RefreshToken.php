<?php

namespace App\Models;

// ══════════════════════════════════════════════════════════════════════════════
// RefreshToken
// ══════════════════════════════════════════════════════════════════════════════
// One row per issued/rotated refresh token. `family_id` is shared by every
// token descended from one original login — reuse of an already-revoked
// token in a family means the whole family is compromised (see
// RefreshTokenService::rotate()), not just an individual expired token.

class RefreshToken extends BaseModel
{
    protected $table = 'refresh_tokens';

    protected $fillable = [
        'user_id',
        'family_id',
        'token_hash',
        'device_name',
        'ip_address',
        'expires_at',
        'revoked_at',
        'last_used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
