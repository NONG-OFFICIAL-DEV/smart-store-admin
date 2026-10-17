<?php

namespace App\Services;

use App\Models\EmenuSetting;

class EmenuSettingService
{
    public function getSettings(): array
    {
        $setting = EmenuSetting::current();

        return [
            'base_url' => $setting->base_url,
            'resolved_base_url' => EmenuSetting::baseUrl(),
        ];
    }

    public function updateSettings(array $data): array
    {
        $setting = EmenuSetting::current();

        // Blank means "clear the override, fall back to .env" — not
        // "do nothing," unlike TelegramSetting's token field (which has
        // a separate "don't re-send the real value" reason to treat
        // blank as no-op). This field has no secrecy concern.
        $setting->update(['base_url' => $data['base_url'] ?: null]);

        return $this->getSettings();
    }
}
