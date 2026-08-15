<?php

namespace App\Models;

// ══════════════════════════════════════════════════════════════════════════════
// TelegramLinkToken
// ══════════════════════════════════════════════════════════════════════════════
// One-time deep-link token (t.me/<bot>?start=<token>) that matches an
// incoming Telegram chat to a specific user — without it, TelegramPoll
// would have no way to tell WHICH user just said /start.

class TelegramLinkToken extends BaseModel
{
    protected $table = 'telegram_link_tokens';

    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
