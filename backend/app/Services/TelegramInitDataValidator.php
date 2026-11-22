<?php

namespace App\Services;

/**
 * Validates the `initData` string a Telegram Mini App sends with every
 * request (window.Telegram.WebApp.initData) — the only thing proving a
 * request genuinely came from Telegram rather than a forged "I am customer
 * X" call. Algorithm per Telegram's own docs: HMAC-SHA256 the sorted
 * "key=value" lines (minus `hash` itself) with a secret derived from the
 * bot token, compare to the `hash` field.
 */
class TelegramInitDataValidator
{
    // How old an initData payload may be before it's rejected — Telegram
    // mini apps reopen often, so this is generous, not a tight session TTL.
    private const MAX_AGE_SECONDS = 86400;

    public function validate(string $initData, string $botToken): ?array
    {
        parse_str($initData, $data);

        $hash = $data['hash'] ?? null;
        if (! $hash || ! is_string($hash)) {
            return null;
        }
        unset($data['hash']);

        $dataCheckString = collect($data)
            ->sortKeys()
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode("\n");

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($computedHash, $hash)) {
            return null;
        }

        $authDate = (int) ($data['auth_date'] ?? 0);
        if ($authDate <= 0 || (time() - $authDate) > self::MAX_AGE_SECONDS) {
            return null;
        }

        $user = isset($data['user']) ? json_decode($data['user'], true) : null;
        if (! is_array($user) || ! isset($user['id'])) {
            return null;
        }

        return [
            'telegram_user_id' => (string) $user['id'],
            'first_name' => $user['first_name'] ?? null,
            'last_name' => $user['last_name'] ?? null,
            'start_param' => $data['start_param'] ?? null,
        ];
    }
}
