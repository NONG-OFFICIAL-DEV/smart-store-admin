<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use Illuminate\Support\Facades\Cache; // for storing last offset
use Illuminate\Support\Facades\DB;

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
    protected $description = 'Poll Telegram bot for messages';

    /**
     * Execute the console command.
     */
    private $botToken = '8580655335:AAG2XhuPJjW7zQMjikd8ZFpqkqPvjSeTBD8';
    private $offset = 0; // track last update_id

    /**
     * Execute the console command.
     */
     public function handle()
    {
        $token = '8580655335:AAG2XhuPJjW7zQMjikd8ZFpqkqPvjSeTBD8'; // or hardcode
        $response = Http::get("https://api.telegram.org/bot{$token}/getUpdates");
        $data = $response->json();

        if (!isset($data['result'])) {
            $this->info('No updates found.');
            return;
        }

        foreach ($data['result'] as $update) {
            $chatId = $update['message']['chat']['id'] ?? null;
            $text   = $update['message']['text'] ?? '';

            if ($text === '/start' && $chatId) {
                DB::table('telegram_pending_links')->updateOrInsert(
                    ['chat_id' => $chatId],
                    ['created_at' => now()]
                );
                $this->info("Saved chat_id: {$chatId}");
            }
        }

        $this->info('Done fetching Telegram updates.');
    }

    private function sendMessage($chatId, $text)
    {
        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";

        Http::post($url, [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);
    }
}
