<?php

namespace App\Models;

// ══════════════════════════════════════════════════════════════════════════════
// TelegramSetting
// ══════════════════════════════════════════════════════════════════════════════
// Single-row platform config for the Telegram bot — no per-tenant scoping,
// there's one bot for the whole platform. DB value (set from the admin
// settings page) takes priority over the .env fallback, so ops can still
// bootstrap via .env before anyone has visited the settings page.

class TelegramSetting extends BaseModel
{
    protected $table = 'telegram_settings';

    protected $fillable = [
        'bot_token',
        'bot_username',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? static::create([]);
    }

    public static function token(): ?string
    {
        return static::current()->bot_token ?: config('services.telegram.bot_token');
    }

    public static function username(): ?string
    {
        return static::current()->bot_username ?: config('services.telegram.bot_username');
    }
}
