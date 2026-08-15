<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// No-ops on its own (see TelegramPoll) if TELEGRAM_BOT_TOKEN isn't set yet.
Schedule::command('telegram:fetch-updates')->everyMinute();
