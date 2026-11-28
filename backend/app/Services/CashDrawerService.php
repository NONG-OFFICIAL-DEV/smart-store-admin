<?php

namespace App\Services;

use App\Models\CashDrawer;
use App\Models\CashTransaction;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Staff;
use App\Repositories\Contracts\CashDrawerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashDrawerService extends BaseService
{
    public function __construct(CashDrawerRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    // Enforced invariant: at most ONE open (unclosed) CashDrawer per branch
    // at a time, regardless of which register it's attached to — a branch
    // can have several named registers configured, but only one of them can
    // actually be "live" simultaneously. This keeps findOpenForBranch()
    // (used by POS checkout gating and the refund flow) unambiguous — there
    // is always exactly zero or one answer for "which drawer is open right
    // now on this branch," never a choice between several.
    public function open(array $data): CashDrawer
    {
        $branchId = $data['branch_id'];

        if ($this->findOpenForBranch($branchId)) {
            throw ValidationException::withMessages([
                'branch_id' => ['This branch already has an open cash register shift — close it before opening another.'],
            ]);
        }

        // Set opened_at explicitly from the app clock (PHP now()) rather
        // than relying on the column's DB-side useCurrent() default —
        // close()/status() below compare it against payments' paid_at,
        // which is also app-clock-generated (e.g. Payment::create with
        // paid_at => now()). Mixing a DB-clock timestamp with app-clock
        // timestamps in the same >= comparison risks excluding a payment
        // made in the same instant the drawer opened if the two clocks
        // disagree by even a fraction of a second.
        return $this->repository->create([
            'branch_id' => $branchId,
            'cash_register_id' => $data['cash_register_id'] ?? null,
            // null for an owner opening their own register — owners have no
            // Staff row at all (see Order.cashier_id/Payment.staff_id for
            // the same established nullable-for-owner pattern).
            'staff_id' => $data['staff_id'] ?? null,
            'opening_float' => $data['opening_float'] ?? 0,
            'opened_at' => now(),
        ]);
    }

    public function findOpenForBranch(string $branchId): ?CashDrawer
    {
        return CashDrawer::where('branch_id', $branchId)->whereNull('closed_at')->first();
    }

    /**
     * Live reconciliation figures for an OPEN (or just-closed) drawer —
     * the exact formula the spec requires:
     *   expected_cash = opening_float + cash_sales + cash_in - cash_out - cash_refunds
     *
     * cash_sales is read via Payment.cash_drawer_id (the real FK, set at
     * checkout time going forward — see CoffeePOSorderController/
     * MartPosController) rather than the old branch+paid_at-range
     * inference, since every cash payment from now on is required to have
     * an open drawer to attribute to (gated at checkout). non_cash_sales
     * has no drawer-session concept of its own (gating only applies to
     * cash), so it's still scoped by branch_id + the drawer's open period —
     * it's reported alongside the drawer purely for visibility, not
     * reconciled against physical cash.
     */
    private function aggregates(CashDrawer $drawer): array
    {
        $periodEnd = $drawer->closed_at ?? now();

        $cashSales = (float) Payment::where('cash_drawer_id', $drawer->id)
            ->where('payment_method', 'cash')
            ->where('status', 'completed')
            ->sum('amount');

        $nonCashSales = (float) Payment::where('branch_id', $drawer->branch_id)
            ->where('payment_method', '!=', 'cash')
            ->where('status', 'completed')
            ->where('paid_at', '>=', $drawer->opened_at)
            ->where('paid_at', '<=', $periodEnd)
            ->sum('amount');

        $cashIn = (float) CashTransaction::where('cash_drawer_id', $drawer->id)
            ->where('type', 'cash_in')
            ->sum('amount');

        $cashOut = (float) CashTransaction::where('cash_drawer_id', $drawer->id)
            ->where('type', 'cash_out')
            ->sum('amount');

        // System-generated cash-outs from a refund paid back in cash — NOT
        // a CashTransaction row, that table is for MANUAL cash in/out only
        // (see RefundService::create()).
        $cashRefunds = (float) Refund::where('cash_drawer_id', $drawer->id)
            ->where('method', 'cash')
            ->sum('amount');

        $openingFloat = (float) $drawer->opening_float;
        $expectedCash = $openingFloat + $cashSales + $cashIn - $cashOut - $cashRefunds;

        return [
            'opening_float' => $openingFloat,
            'cash_sales' => $cashSales,
            'non_cash_sales' => $nonCashSales,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'cash_refunds' => $cashRefunds,
            'expected_cash' => $expectedCash,
        ];
    }

    public function status(CashDrawer $drawer): array
    {
        return $this->aggregates($drawer);
    }

    public function close(CashDrawer $drawer, float $actualCash, ?string $notes = null): CashDrawer
    {
        $expectedCash = $this->aggregates($drawer)['expected_cash'];

        return $this->repository->update($drawer, [
            'expected_cash' => $expectedCash,
            'actual_cash' => $actualCash,
            'variance' => $actualCash - $expectedCash,
            'closed_at' => now(),
            'notes' => $notes,
        ]);
    }

    public function cashIn(CashDrawer $drawer, float $amount, string $reason, ?Staff $staff): CashTransaction
    {
        return DB::transaction(function () use ($drawer, $amount, $reason, $staff) {
            $locked = CashDrawer::where('id', $drawer->id)->lockForUpdate()->firstOrFail();

            if ($locked->closed_at) {
                throw ValidationException::withMessages([
                    'cash_drawer_id' => ['This shift is already closed.'],
                ]);
            }

            return CashTransaction::create([
                'cash_drawer_id' => $locked->id,
                // null for an owner recording their own cash movement — see
                // open() above for the same nullable-for-owner reasoning.
                'staff_id' => $staff?->id,
                'type' => 'cash_in',
                'amount' => $amount,
                'reason' => $reason,
            ]);
        });
    }

    public function cashOut(CashDrawer $drawer, float $amount, string $reason, ?Staff $staff): CashTransaction
    {
        return DB::transaction(function () use ($drawer, $amount, $reason, $staff) {
            // Locked for the duration of the balance check + insert so two
            // concurrent cash-outs can't both read the same stale balance
            // and both succeed when only one should.
            $locked = CashDrawer::where('id', $drawer->id)->lockForUpdate()->firstOrFail();

            if ($locked->closed_at) {
                throw ValidationException::withMessages([
                    'cash_drawer_id' => ['This shift is already closed.'],
                ]);
            }

            $balance = $this->aggregates($locked)['expected_cash'];
            if ($amount > $balance) {
                throw ValidationException::withMessages([
                    'amount' => ["Insufficient cash balance ({$balance}) for this cash-out."],
                ]);
            }

            return CashTransaction::create([
                'cash_drawer_id' => $locked->id,
                'staff_id' => $staff?->id,
                'type' => 'cash_out',
                'amount' => $amount,
                'reason' => $reason,
            ]);
        });
    }

    public function update(CashDrawer $drawer, array $data): CashDrawer
    {
        return $this->repository->update($drawer, $data);
    }

    public function delete(CashDrawer $drawer): bool
    {
        return $this->repository->delete($drawer);
    }
}
