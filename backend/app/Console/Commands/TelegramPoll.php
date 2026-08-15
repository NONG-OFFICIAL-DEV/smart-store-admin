<?php

namespace App\Console\Commands;

use App\Models\TelegramLinkToken;
use App\Models\TelegramSetting;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TelegramPoll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:fetch-updates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll Telegram for /start <token> deep-links and complete pending chat links';

    private const OFFSET_CACHE_KEY = 'telegram_poll_offset';

    public function handle(TelegramService $telegram): int
    {
        $botToken = TelegramSetting::token();

        if (! $botToken) {
            $this->warn('TELEGRAM_BOT_TOKEN is not configured — skipping.');
            return self::SUCCESS;
        }

        $offset = Cache::get(self::OFFSET_CACHE_KEY, 0);

        $response = Http::get("https://api.telegram.org/bot{$botToken}/getUpdates", [
            'offset' => $offset,
            'timeout' => 0,
        ]);

        $updates = $response->json('result', []);

        foreach ($updates as $update) {
            Cache::put(self::OFFSET_CACHE_KEY, $update['update_id'] + 1);

            $chatId = $update['message']['chat']['id'] ?? null;
            $text = trim($update['message']['text'] ?? '');

            if (! $chatId || ! str_starts_with($text, '/start')) {
                continue;
            }

            $this->handleStart($telegram, (string) $chatId, trim(substr($text, 6)));
        }

        $this->info('Done fetching Telegram updates. Processed: ' . count($updates));

        return self::SUCCESS;
    }

    private function handleStart(TelegramService $telegram, string $chatId, string $linkToken): void
    {
        $pending = TelegramLinkToken::where('token', $linkToken)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $pending) {
            $telegram->send($chatId, 'This link is invalid or has expired. Please generate a new one from the app.');
            return;
        }

        $pending->user->update([
            'telegram_chat_id' => $chatId,
            'notify_telegram' => true,
        ]);
        $pending->update(['used_at' => now()]);

        $telegram->send($chatId, "\u{2705} Linked! You'll now receive notifications here.");
    }
}
