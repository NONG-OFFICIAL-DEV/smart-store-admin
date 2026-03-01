<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Http;
class NotificationService
{
    /**
     * Create a new notification for a user.
     *
     * @param User $user
     * @param string $title
     * @param string $message
     * @param array $options Optional keys: icon, color, type, action_url, channel
     * @return Notification
     */
    public function create(
        User $user,
        string $title,
        string $message,
        array $options = []
    ): Notification {
        return Notification::create([
            'user_id'    => $user->id,
            'title'      => $title,
            'message'    => $message,
            'icon'       => $options['icon'] ?? 'mdi-bell-outline',
            'color'      => $options['color'] ?? 'primary',
            'type'       => $options['type'] ?? 'info',
            'action_url' => $options['action_url'] ?? null,
            'channel'    => $options['channel'] ?? 'system', // web, telegram, email...
            'is_read'    => false,
            'data'       => $options['data'] ?? null,
        ]);
    }

    /**
     * Optionally send to Telegram (if linked)
     */
    public function sendTelegram(string $telegram_chat_id, string $title, string $message)
    {
        // if (!$user->telegram_chat_id) return false;

        $botToken = '8580655335:AAG2XhuPJjW7zQMjikd8ZFpqkqPvjSeTBD8';
        // $chatId = $user->telegram_chat_id;

        $text = "*{$title}*\n{$message}";

        return Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $telegram_chat_id,
            'text'    => $text,
            'parse_mode' => 'Markdown',
        ]);
    }
}
