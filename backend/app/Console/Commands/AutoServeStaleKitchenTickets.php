<?php

namespace App\Console\Commands;

use App\Models\KitchenDisplayTicket;
use App\Models\OrderStatusHistory;
use Illuminate\Console\Command;

/**
 * Safety net for the exact scenario that prompted this: a branch that
 * doesn't actually staff/watch its Kitchen Display just accumulates
 * tickets forever (one was seen at 31315 minutes old) with no one around
 * to click Start/Complete/Serve. Runs hourly (see routes/console.php) and
 * auto-serves anything older than the threshold, regardless of which stage
 * it's stuck at — 'new', 'in_progress', or 'done' all count as forgotten.
 *
 * The threshold is per-branch (branches.kitchen_auto_serve_hours, see
 * Settings > Kitchen) since some branches run a much faster/slower service
 * than others — --hours overrides every branch's own setting for a single
 * run (manual testing/tuning), it doesn't change what's stored.
 */
class AutoServeStaleKitchenTickets extends Command
{
    protected $signature = 'kitchen:auto-serve-stale {--hours= : Override every branch\'s own threshold for this run only}';

    protected $description = 'Auto-marks orders served once their kitchen ticket has sat untouched past its branch\'s age threshold.';

    public function handle(): int
    {
        $override = $this->option('hours');
        $now = now();

        // Can't filter by age at the DB level anymore since the threshold
        // varies per branch — loaded in full (non-cancelled only) and
        // filtered in PHP instead. Ticket volumes here are small enough
        // that this is still cheap.
        $tickets = KitchenDisplayTicket::with(['order', 'branch'])
            ->where('status', '!=', 'cancelled')
            ->get()
            ->filter(function ($ticket) use ($override, $now) {
                if (!$ticket->order || in_array($ticket->order->status, ['served', 'cancelled', 'refunded'])) {
                    return false;
                }
                $hours = $this->thresholdFor($ticket, $override);

                return $ticket->created_at <= $now->copy()->subHours($hours);
            });

        if ($tickets->isEmpty()) {
            $this->info('No stale tickets to auto-serve.');

            return self::SUCCESS;
        }

        foreach ($tickets as $ticket) {
            $order = $ticket->order;
            $fromStatus = $order->status;
            $hours = $this->thresholdFor($ticket, $override);

            // Deliberately does NOT touch completed_at — that's the
            // payment-completion timestamp, unrelated to whether food
            // physically reached the customer (see OrderController::serve()).
            $order->update(['status' => 'served']);
            if ($ticket->status !== 'done') {
                $ticket->update(['status' => 'done']);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $fromStatus,
                'to_status' => 'served',
                'notes' => "Auto-served — kitchen ticket untouched for over {$hours}h",
            ]);
        }

        $this->info("Auto-served {$tickets->count()} stale order(s).");

        return self::SUCCESS;
    }

    private function thresholdFor(KitchenDisplayTicket $ticket, mixed $override): int
    {
        if ($override !== null) {
            return (int) $override;
        }

        return $ticket->branch->kitchen_auto_serve_hours ?? 12;
    }
}
