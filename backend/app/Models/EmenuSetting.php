<?php

namespace App\Models;

// ══════════════════════════════════════════════════════════════════════════════
// EmenuSetting
// ══════════════════════════════════════════════════════════════════════════════
// Single-row platform config for the digital-menu/table-QR base URL — no
// per-tenant scoping, one value for the whole platform. DB value (set from
// the super-admin settings page) takes priority over the .env fallback, so
// ops can still bootstrap via .env before anyone has visited the settings
// page. Kept separate from TelegramSetting/config('app.frontend_url') —
// the e-menu link and the admin app's own URL (used for password-reset
// emails) are logically different things even though they share one
// default today.

class EmenuSetting extends BaseModel
{
    protected $table = 'emenu_settings';

    protected $fillable = [
        'base_url',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? static::create([]);
    }

    public static function baseUrl(): string
    {
        return rtrim(static::current()->base_url ?: config('app.frontend_url'), '/');
    }
}
