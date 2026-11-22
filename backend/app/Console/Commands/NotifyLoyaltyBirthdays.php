<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\LoyaltySetting;
use App\Services\TelegramService;
use Illuminate\Console\Command;

/**
 * Runs daily (see routes/console.php). A plain congratulatory message for
 * now — Phase 1 doesn't define a "birthday bonus stamps" setting, so this
 * doesn't grant points, just the notification itself (which the tenant and
 * the customer can each independently turn off).
 */
class NotifyLoyaltyBirthdays extends Command
{
    protected $signature = 'loyalty:notify-birthdays';

    protected $description = 'Sends a birthday-gift message to customers whose birthday is today.';

    public function __construct(private TelegramService $telegram)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = now();

        // Console commands run with no authenticated user, so TenantScope
        // no-ops entirely — this naturally sees every tenant's customers.
        $customers = Customer::whereNotNull('date_of_birth')
            ->whereNotNull('telegram_chat_id')
            ->where('notify_telegram', true)
            ->whereRaw('extract(month from date_of_birth) = ?', [$today->month])
            ->whereRaw('extract(day from date_of_birth) = ?', [$today->day])
            ->get();

        $sent = 0;

        foreach ($customers as $customer) {
            $settings = LoyaltySetting::forTenant($customer->tenant_id);

            if (! $settings->notify_birthday) {
                continue;
            }

            $this->telegram->send(
                $customer->telegram_chat_id,
                "🎂 Happy Birthday, {$customer->first_name}! Thanks for being a loyal customer — drop by today for a little something on us."
            );
            $sent++;
        }

        $this->info("Sent {$sent} birthday notification(s).");

        return self::SUCCESS;
    }
}
