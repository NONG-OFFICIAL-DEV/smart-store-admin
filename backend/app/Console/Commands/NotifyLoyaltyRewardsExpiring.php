<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\LoyaltyReward;
use App\Models\LoyaltySetting;
use App\Services\TelegramService;
use Illuminate\Console\Command;

/**
 * Runs daily (see routes/console.php). Only fires on the single day a
 * reward's valid_until crosses the "N days out" threshold — not a
 * >= now <= +N range — so each reward notifies its eligible customers
 * exactly once, with no separate "already notified" tracking table needed.
 */
class NotifyLoyaltyRewardsExpiring extends Command
{
    protected $signature = 'loyalty:notify-rewards-expiring';

    protected $description = "Tells customers who can already afford a reward that it's about to expire.";

    private const DAYS_BEFORE = 3;

    public function __construct(private TelegramService $telegram)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $targetDate = now()->addDays(self::DAYS_BEFORE)->toDateString();

        // Console commands run with no authenticated user, so TenantScope
        // no-ops entirely — this naturally sees every tenant's rewards.
        $rewards = LoyaltyReward::where('is_active', true)
            ->whereDate('valid_until', $targetDate)
            ->get();

        $sent = 0;

        foreach ($rewards as $reward) {
            $settings = LoyaltySetting::forTenant($reward->tenant_id);

            if (! $settings->notify_reward_expiring) {
                continue;
            }

            $customers = Customer::where('tenant_id', $reward->tenant_id)
                ->where('loyalty_points', '>=', $reward->points_cost)
                ->where('notify_telegram', true)
                ->whereNotNull('telegram_chat_id')
                ->get();

            foreach ($customers as $customer) {
                $this->telegram->send(
                    $customer->telegram_chat_id,
                    "⏳ \"{$reward->name}\" expires in ".self::DAYS_BEFORE." days — you have enough stamps, redeem it before it's gone!"
                );
                $sent++;
            }
        }

        $this->info("Sent {$sent} reward-expiring notification(s).");

        return self::SUCCESS;
    }
}
