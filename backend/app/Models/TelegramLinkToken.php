<?php

namespace App\Models;

// ══════════════════════════════════════════════════════════════════════════════
// TelegramLinkToken
// ══════════════════════════════════════════════════════════════════════════════
// One-time deep-link token (t.me/<bot>?start=<token>) that matches an
// incoming Telegram chat to a specific user or customer — without it,
// TelegramPoll would have no way to tell WHO just said /start. Exactly one
// of user_id/customer_id is set per row (enforced in TelegramService).

class TelegramLinkToken extends BaseModel
{
    protected $table = 'telegram_link_tokens';

    protected $fillable = [
        'user_id',
        'customer_id',
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

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
