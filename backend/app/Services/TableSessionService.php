<?php

namespace App\Services;

use App\Exceptions\PaySessionException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Table;
use App\Models\TableSession;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// ─────────────────────────────────────────────────────────────────────────────
// Single shared place for everything table-session related: opening/reusing
// a table's running tab, building its merged bill, and keeping both the
// session and the table's status in sync once orders are paid/cancelled.
// Every dine-in order-creation path (OrderController::store(), Coffee/Mart
// POS controllers) and every order status transition (Order::updateStatus())
// goes through this service rather than touching `table_sessions`/`tables`
// directly, so there's exactly one place that can get the rules wrong.
// ─────────────────────────────────────────────────────────────────────────────
class TableSessionService
{
    // Same "still open" rule as the old openOrdersForTable() query (not
    // cancelled/refunded, not yet paid) — a session has nothing left owing
    // once every one of its orders fails this check.
    private function hasActiveOrder(string $sessionId): bool
    {
        return Order::where('table_session_id', $sessionId)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->whereDoesntHave('payments')
            ->exists();
    }

    public function getOpenSession(string $tableId): ?TableSession
    {
        return TableSession::where('table_id', $tableId)
            ->where('status', TableSession::STATUS_OPEN)
            ->first();
    }

    public function isTableOccupied(string $tableId): bool
    {
        return $this->getOpenSession($tableId) !== null;
    }

    // Reuses the table's current open session if one exists; otherwise opens
    // a new one and marks the table occupied. The partial unique index on
    // table_sessions (table_id) WHERE status='open' is what actually
    // guarantees "one open session per table" under concurrent requests —
    // this just makes losing that race a clean re-fetch instead of a 500.
    public function getOrCreateOpenSession(string $tableId): TableSession
    {
        $existing = $this->getOpenSession($tableId);
        if ($existing) {
            return $existing;
        }

        $table = Table::findOrFail($tableId);

        try {
            $session = TableSession::create([
                'table_id'  => $tableId,
                'branch_id' => $table->branch_id,
                'status'    => TableSession::STATUS_OPEN,
                'opened_at' => now(),
                'opened_by' => auth()->user()?->staff?->id,
            ]);
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) == 23505 || str_contains($e->getMessage(), 'table_sessions_one_open_per_table')) {
                return $this->getOpenSession($tableId) ?? throw $e;
            }
            throw $e;
        }

        $this->syncTableStatus($tableId);

        return $session;
    }

    // Every non-cancelled/refunded order in the session, items merged by
    // product + variant + unit price + modifier set — "Coca x1" on one
    // ticket and "Coca x1" on another becomes one "Coca x2" line, the same
    // merge OrderDetailDialog.vue already does client-side today, just
    // computed once here so every consumer (bill, receipt, Pay dialog) sees
    // the identical total. Returns {session: null} for a closed/missing
    // session rather than erroring — callers render "No open bill" from that.
    public function getSessionBill(string $sessionId): array
    {
        $session = TableSession::with('table')->find($sessionId);
        if (!$session || $session->status !== TableSession::STATUS_OPEN) {
            return ['session' => null];
        }

        $orders = Order::where('table_session_id', $sessionId)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->with(['items.modifiers'])
            ->get();

        return [
            'session' => [
                'id'        => $session->id,
                'table_id'  => $session->table_id,
                'status'    => $session->status,
                'opened_at' => $session->opened_at,
            ],
            ...$this->summarize($orders),
        ];
    }

    // Shared by getSessionBill() (an open, still-unpaid session) and
    // paySession()'s receipt (the same orders, now paid) — one merge rule,
    // one totals rule, for every consumer of "what does this session add
    // up to."
    private function summarize(Collection $orders): array
    {
        $merged = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $modifiersKey = $item->modifiers
                    ->pluck('modifier_option_id')
                    ->sort()
                    ->implode(',');
                $key = implode('|', [$item->product_id, $item->variant_id ?? '', (string) $item->unit_price, $modifiersKey]);

                $merged[$key] ??= [
                    'product_id'   => $item->product_id,
                    'variant_id'   => $item->variant_id,
                    'product_name' => $item->product_name,
                    'unit_price'   => (float) $item->unit_price,
                    'quantity'     => 0,
                    'total_price'  => 0,
                    'modifiers'    => $item->modifiers->map(fn($m) => [
                        'name'  => $m->option_name,
                        'price' => (float) $m->price_adjustment,
                    ])->values(),
                ];

                $merged[$key]['quantity']    += $item->quantity;
                $merged[$key]['total_price'] += (float) $item->total_price;
            }
        }

        return [
            'order_count'    => $orders->count(),
            'order_ids'      => $orders->pluck('id')->values(),
            'items'          => array_values($merged),
            'subtotal'       => (float) $orders->sum('subtotal'),
            'discount'       => (float) $orders->sum('discount_amount'),
            'tax'            => (float) $orders->sum('tax_amount'),
            'service_charge' => (float) $orders->sum('service_charge_amount'),
            'total'          => (float) $orders->sum('total_amount'),
        ];
    }

    // ── PATCH /api/v1/table-sessions/{tableSession}/pay ─────────────────────
    // The fix for "PAY only pays one order": every non-cancelled, unpaid
    // order in the session is paid and closed together, in one transaction,
    // instead of the caller having to pay each order one at a time. Still
    // writes one Payment row per order (not one pooled row) so every
    // existing "is this order paid" check (Order::payments()) keeps working
    // unchanged — table_session_id on each row is just the paper trail
    // tying them back to the same checkout. Returns the whole session's
    // receipt data (items merged, totals, payment, paid_at) straight from
    // this response — callers must not re-fetch, there'll be nothing open
    // left to fetch once this returns.
    public function paySession(string $sessionId, array $paymentData, ?string $staffId = null): array
    {
        return DB::transaction(function () use ($sessionId, $paymentData, $staffId) {
            $session = TableSession::lockForUpdate()->find($sessionId);
            if (!$session) {
                throw new \RuntimeException('Session not found.');
            }
            if ($session->status !== TableSession::STATUS_OPEN) {
                throw new PaySessionException('This table session is already closed.');
            }

            $orders = Order::where('table_session_id', $sessionId)
                ->whereNotIn('status', ['cancelled', 'refunded'])
                ->whereDoesntHave('payments')
                ->with(['items.modifiers'])
                ->get();

            if ($orders->isEmpty()) {
                throw new PaySessionException('This session has no unpaid orders left to pay.');
            }

            $total = (float) $orders->sum('total_amount');
            $cashTendered = (float) ($paymentData['cash_tendered'] ?? $total);

            if ($paymentData['payment_method'] === 'cash' && $cashTendered < $total) {
                throw new PaySessionException('Cash tendered is less than the session total.');
            }

            $changeGiven = $paymentData['payment_method'] === 'cash' ? max(0, $cashTendered - $total) : null;
            $requestedMethod = $paymentData['payment_method'];
            // Same request vocabulary as OrderController::settlePayment()
            // (cash/card/qr/transfer) mapped onto payments.payment_method's
            // actual DB enum (cash/card/qr_code/online/...).
            $paymentMethod = ['cash' => 'cash', 'card' => 'card', 'qr' => 'qr_code', 'transfer' => 'online'][$requestedMethod] ?? 'cash';
            $branchId = $session->branch_id;

            $orders->each(function (Order $order, int $index) use ($session, $requestedMethod, $paymentMethod, $changeGiven, $branchId, $staffId, $orders) {
                Payment::create([
                    'order_id'         => $order->id,
                    'table_session_id' => $session->id,
                    'branch_id'        => $branchId,
                    'staff_id'         => $staffId,
                    'payment_method'   => $paymentMethod,
                    'amount'           => $order->total_amount,
                    // The customer only hands back/receives one amount of
                    // change for the whole bill — recorded once, on the
                    // last order processed, rather than split/duplicated
                    // across every row in the session.
                    'change_given'     => $index === $orders->count() - 1 ? $changeGiven : null,
                    'currency'         => $order->branch->tenant->currency ?? 'USD',
                    'status'           => 'completed',
                    'paid_at'          => now(),
                ]);

                // Reuses the exact same transition Order::updateStatus()
                // already does for a single order — history log, and
                // (via TableSessionService::syncAfterOrderChange()) closes
                // the session and frees the table once this was the last
                // unpaid order in it.
                Order::updateStatus($order->id, 'completed', $staffId, 'Session payment');
            });

            $session->refresh();

            return [
                'session' => [
                    'id'        => $session->id,
                    'table_id'  => $session->table_id,
                    'status'    => $session->status,
                    'closed_at' => $session->closed_at,
                ],
                ...$this->summarize($orders),
                'payment' => [
                    'method'        => $paymentMethod,
                    'cash_tendered' => $cashTendered,
                    'change_given'  => (float) ($changeGiven ?? 0),
                    'paid_at'       => now(),
                ],
            ];
        });
    }

    // Cancelling an order (or it getting fully paid) can leave a session
    // with nothing left owing — close it so the table frees up and the next
    // customer seated there starts a brand new session rather than joining
    // a stale one.
    public function closeSessionIfEmpty(string $sessionId): void
    {
        $session = TableSession::find($sessionId);
        if (!$session || $session->status !== TableSession::STATUS_OPEN) {
            return;
        }

        if ($this->hasActiveOrder($sessionId)) {
            return;
        }

        $session->update(['status' => TableSession::STATUS_CLOSED, 'closed_at' => now()]);
        $this->syncTableStatus($session->table_id);
    }

    // Call after anything that can change an order's session/paid/cancelled
    // state. Closes the order's session if it's now empty, then resyncs the
    // table's stored status to match — but only between available/occupied;
    // reserved/cleaning/inactive are staff-set states with no order behind
    // them and must never be clobbered by this.
    public function syncAfterOrderChange(Order $order): void
    {
        if ($order->table_session_id) {
            $this->closeSessionIfEmpty($order->table_session_id);
        }
        if ($order->table_id) {
            $this->syncTableStatus($order->table_id);
        }
    }

    public function syncTableStatus(string $tableId): void
    {
        $table = Table::find($tableId);
        if (!$table || !in_array($table->status, [Table::STATUS_AVAILABLE, Table::STATUS_OCCUPIED], true)) {
            return;
        }

        Table::updateStatus($tableId, $this->isTableOccupied($tableId) ? Table::STATUS_OCCUPIED : Table::STATUS_AVAILABLE);
    }
}
