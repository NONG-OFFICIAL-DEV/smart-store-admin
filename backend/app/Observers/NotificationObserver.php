<?php

namespace App\Observers;

use App\Events\NotificationCreated;
use App\Mail\NotificationMail;
use App\Models\Notification;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationObserver
{
    public function __construct(private TelegramService $telegram)
    {
    }

    /**
     * Single fan-out point for every current and future call site that
     * creates a user-targeted Notification row — the 3 delivery channels
     * (system live-push, email, Telegram) all gate off the recipient's
     * own preferences here, so no call site needs to know about any of
     * them. The row itself is always created regardless of preference —
     * it's the base source of truth for the notifications page/bell list,
     * only the *live push* is what "system" actually toggles.
     *
     * Each channel is isolated in its own try/catch — e.g. ShouldBroadcastNow
     * throws synchronously if Reverb isn't reachable, and without this that
     * one channel failing would silently prevent email/Telegram from ever
     * being attempted (and would bubble up and fail the original
     * Notification::create() call at whatever call site triggered it).
     */
    public function created(Notification $notification): void
    {
        if (! $notification->user_id) {
            return;
        }

        $user = $notification->user;

        if (! $user) {
            return;
        }

        if ($user->notify_system) {
            $this->attempt('system', fn () => NotificationCreated::dispatch($notification));
        }

        if ($user->notify_email && $user->email) {
            $this->attempt('email', fn () => Mail::to($user->email)->queue(new NotificationMail($notification)));
        }

        if ($user->notify_telegram && $user->telegram_chat_id) {
            $this->attempt('telegram', fn () => $this->telegram->send(
                $user->telegram_chat_id,
                "{$notification->title}\n{$notification->body}"
            ));
        }
    }

    private function attempt(string $channel, callable $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            Log::warning("Notification channel [{$channel}] failed to deliver", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
