<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Table;
use Illuminate\Console\Command;

/**
 * One-time repair for tables whose stored `status` drifted out of sync with
 * their orders (e.g. settle-payment previously didn't free the table — see
 * Order::syncTableStatus()). Only ever flips between 'available' and
 * 'occupied'; 'reserved'/'cleaning' are staff-set states with no order
 * behind them and are left untouched. Dry-run by default — pass --apply to
 * actually write the changes.
 */
class SyncTableStatuses extends Command
{
    protected $signature = 'tables:sync-status {--apply : Actually update the rows instead of just reporting}';

    protected $description = 'Recompute every table\'s occupied/available status from its orders and report (or fix) any drift.';

    public function handle(): int
    {
        $tables = Table::whereIn('status', [Table::STATUS_AVAILABLE, Table::STATUS_OCCUPIED])->get();

        $changes = [];

        foreach ($tables as $table) {
            $hasOpenOrder = Order::where('table_id', $table->id)
                ->whereNotIn('status', ['cancelled', 'refunded'])
                ->whereDoesntHave('payments')
                ->exists();

            $correctStatus = $hasOpenOrder ? Table::STATUS_OCCUPIED : Table::STATUS_AVAILABLE;

            if ($table->status !== $correctStatus) {
                $changes[] = [$table->id, $table->table_number, $table->status, $correctStatus];
            }
        }

        if (empty($changes)) {
            $this->info('All tables already match their orders. Nothing to do.');

            return self::SUCCESS;
        }

        $this->table(['Table ID', 'Table', 'Current', 'Should be'], $changes);
        $this->info(count($changes) . ' table(s) would change.');

        if (! $this->option('apply')) {
            $this->comment('Dry run only — re-run with --apply to update these rows.');

            return self::SUCCESS;
        }

        foreach ($changes as [$id, , , $correctStatus]) {
            Table::where('id', $id)->update(['status' => $correctStatus]);
        }

        $this->info('Updated ' . count($changes) . ' table(s).');

        return self::SUCCESS;
    }
}
