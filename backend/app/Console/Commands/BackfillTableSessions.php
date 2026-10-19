<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Table;
use App\Models\TableSession;
use App\Services\TableSessionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time backfill for Phase 1 of the table-sessions redesign: groups each
 * table's existing unpaid, non-cancelled/refunded dine-in orders into one
 * open TableSession (one per table — matches the DB's own partial unique
 * index), leaves paid/cancelled orders with table_session_id = null, and
 * resyncs every table's available/occupied status to match afterward. Dry
 * run by default — pass --apply to actually write the changes.
 */
class BackfillTableSessions extends Command
{
    protected $signature = 'tables:backfill-sessions {--apply : Actually write the sessions instead of just reporting}';

    protected $description = 'Group existing open dine-in orders into table_sessions and resync table status.';

    public function handle(TableSessionService $tableSessions): int
    {
        $apply = (bool) $this->option('apply');

        $openOrdersByTable = Order::where('order_type', 'dine_in')
            ->whereNotNull('table_id')
            ->whereNull('table_session_id')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->whereDoesntHave('payments')
            ->orderBy('created_at')
            ->get()
            ->groupBy('table_id');

        if ($openOrdersByTable->isEmpty()) {
            $this->info('No open dine-in orders need a session. Nothing to do.');
        } else {
            $rows = [];
            foreach ($openOrdersByTable as $tableId => $orders) {
                $table = Table::find($tableId);
                $rows[] = [$tableId, $table?->table_number ?? '(deleted)', $orders->count(), $orders->sum('total_amount')];
            }

            $this->table(['Table ID', 'Table #', 'Orders', 'Total'], $rows);
            $this->info(count($rows) . ' table(s) would get a new open session covering ' . $openOrdersByTable->flatten(1)->count() . ' order(s).');

            if ($apply) {
                DB::transaction(function () use ($openOrdersByTable) {
                    foreach ($openOrdersByTable as $tableId => $orders) {
                        $table = Table::find($tableId);
                        if (!$table) {
                            continue;
                        }

                        $session = TableSession::create([
                            'table_id' => $tableId,
                            'branch_id' => $table->branch_id,
                            'status' => TableSession::STATUS_OPEN,
                            'opened_at' => $orders->first()->created_at,
                        ]);

                        Order::whereIn('id', $orders->pluck('id'))->update(['table_session_id' => $session->id]);
                    }
                });
                $this->info('Sessions created.');
            }
        }

        // Resync every table's available/occupied status (reserved/cleaning/
        // inactive are left untouched — see TableSessionService::syncTableStatus()).
        $statusChanges = [];
        foreach (Table::whereIn('status', [Table::STATUS_AVAILABLE, Table::STATUS_OCCUPIED])->get() as $table) {
            $shouldBeOccupied = $apply
                ? $tableSessions->isTableOccupied($table->id)
                : ($openOrdersByTable->has($table->id) || $tableSessions->isTableOccupied($table->id));
            $correctStatus = $shouldBeOccupied ? Table::STATUS_OCCUPIED : Table::STATUS_AVAILABLE;

            if ($table->status !== $correctStatus) {
                $statusChanges[] = [$table->id, $table->table_number, $table->status, $correctStatus];
            }
        }

        if (empty($statusChanges)) {
            $this->info('All table statuses already match.');

            return self::SUCCESS;
        }

        $this->table(['Table ID', 'Table #', 'Current', 'Should be'], $statusChanges);
        $this->info(count($statusChanges) . ' table status(es) would change.');

        if (!$apply) {
            $this->comment('Dry run only — re-run with --apply to create sessions and update table statuses.');

            return self::SUCCESS;
        }

        foreach ($statusChanges as [$id, , , $correctStatus]) {
            Table::where('id', $id)->update(['status' => $correctStatus]);
        }
        $this->info('Table statuses updated.');

        return self::SUCCESS;
    }
}
