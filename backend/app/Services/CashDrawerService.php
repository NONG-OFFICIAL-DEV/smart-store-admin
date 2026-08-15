<?php

namespace App\Services;

use App\Models\CashDrawer;
use App\Models\Payment;
use App\Repositories\Contracts\CashDrawerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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

    public function open(array $data): CashDrawer
    {
        // Set opened_at explicitly from the app clock (PHP now()) rather
        // than relying on the column's DB-side useCurrent() default —
        // close() below compares it against payments' paid_at, which is
        // also app-clock-generated (e.g. Payment::create with paid_at =>
        // now()). Mixing a DB-clock timestamp with app-clock timestamps in
        // the same >= comparison risks excluding a payment made in the same
        // instant the drawer opened if the two clocks disagree by even a
        // fraction of a second.
        return $this->repository->create([
            'branch_id' => $data['branch_id'],
            'staff_id' => $data['staff_id'],
            'opening_float' => $data['opening_float'] ?? 0,
            'opened_at' => now(),
        ]);
    }

    // Expected cash = opening float + every completed cash payment on this
    // branch since the drawer opened; variance is actual vs. expected.
    public function close(CashDrawer $drawer, float $actualCash, ?string $notes = null): CashDrawer
    {
        $cashPayments = Payment::where('branch_id', $drawer->branch_id)
            ->where('payment_method', 'cash')
            ->where('status', 'completed')
            ->where('paid_at', '>=', $drawer->opened_at)
            ->sum('amount');

        $expectedCash = $drawer->opening_float + $cashPayments;

        return $this->repository->update($drawer, [
            'expected_cash' => $expectedCash,
            'actual_cash' => $actualCash,
            'variance' => $actualCash - $expectedCash,
            'closed_at' => now(),
            'notes' => $notes,
        ]);
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
