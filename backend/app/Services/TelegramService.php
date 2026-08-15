<?php

namespace App\Services;

use App\Models\TelegramLinkToken;
use App\Models\TelegramSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TelegramService
{
    /**
     * Sends a message to an already-linked chat. Silently skips if no bot
     * token is configured (Telegram is opt-in infra — the user supplies
     * their own token via @BotFather, so this must be a safe no-op until
     * they do).
     */
    public function send(string $chatId, string $text): void
    {
        $token = TelegramSetting::token();

        if (! $token) {
            return;
        }

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);
    }

    /**
     * Issues a one-time deep-link token for this user and returns the
     * t.me URL to send them to — TelegramPoll matches the /start payload
     * back to this token to learn which user just linked their chat.
     */
    public function generateLinkUrl(User $user): string
    {
        $username = TelegramSetting::username();

        TelegramLinkToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();

        $token = TelegramLinkToken::create([
            'user_id' => $user->id,
            'token' => Str::random(32),
            'expires_at' => now()->addMinutes(30),
        ]);

        return "https://t.me/{$username}?start={$token->token}";
    }

    public function unlink(User $user): User
    {
        $user->update([
            'telegram_chat_id' => null,
            'notify_telegram' => false,
        ]);

        return $user;
    }

    /**
     * Never returns the raw token — a "has one configured + last 4 chars"
     * preview is enough for an admin to confirm it's set without this
     * endpoint becoming a way to read the live secret back out.
     */
    public function getSettings(): array
    {
        $setting = TelegramSetting::current();

        return [
            'bot_username' => $setting->bot_username,
            'has_token' => (bool) $setting->bot_token,
            'token_preview' => $setting->bot_token ? '••••'.substr($setting->bot_token, -4) : null,
        ];
    }

    public function updateSettings(array $data): array
    {
        $setting = TelegramSetting::current();

        // Blank/omitted bot_token means "leave the current one alone" —
        // the settings page never receives the real value back to re-submit.
        if (array_key_exists('bot_token', $data) && $data['bot_token'] === '') {
            unset($data['bot_token']);
        }

        $setting->update($data);

        return $this->getSettings();
    }

    public function testConnection(): array
    {
        $token = TelegramSetting::token();

        if (! $token) {
            return ['ok' => false, 'error' => 'No bot token configured.'];
        }

        $response = Http::get("https://api.telegram.org/bot{$token}/getMe");

        if (! $response->successful() || ! $response->json('ok')) {
            return ['ok' => false, 'error' => $response->json('description', 'Telegram rejected this token.')];
        }

        return ['ok' => true, 'bot_username' => $response->json('result.username')];
    }
}
