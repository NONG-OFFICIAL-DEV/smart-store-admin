<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Repositories\Contracts\RefundRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class RefundService extends BaseService
{
    public function __construct(
        RefundRepositoryInterface $repository,
        private CashDrawerService $cashDrawers
    ) {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    /**
     * The original (dead — never wired to a route) Refund::store() always
     * marked the payment and order fully "refunded" regardless of the
     * refund amount, with no check against how much of the payment had
     * already been refunded — multiple refunds could exceed the payment
     * total. Fixed here: reject a refund that would over-refund the
     * payment, and only mark the payment/order fully refunded once the
     * cumulative refunded amount actually covers it (partial refunds leave
     * the payment as partially_refunded instead).
     */
    public function create(Payment $payment, array $data): Refund
    {
        $alreadyRefunded = $payment->refunds()->sum('amount');
        $remaining = $payment->amount - $alreadyRefunded;

        if ($data['amount'] > $remaining) {
            throw ValidationException::withMessages([
                'amount' => "Refund amount exceeds the remaining refundable amount ({$remaining}).",
            ]);
        }

        $data['payment_id'] = $payment->id;
        $data['order_id'] = $payment->order_id;
        $data['status'] = 'completed';

        // A cash refund physically comes out of whichever drawer is open
        // RIGHT NOW on the payment's branch — not necessarily the one open
        // at the time of the original sale. No open drawer means there's
        // nowhere to record the cash leaving from, so reject rather than
        // silently losing it from reconciliation (cash_drawer_id would stay
        // null and CashDrawerService's expected-cash math would never see
        // this refund at all).
        if (($data['method'] ?? 'original_method') === 'cash') {
            $openDrawer = $this->cashDrawers->findOpenForBranch($payment->branch_id);

            if (! $openDrawer) {
                throw ValidationException::withMessages([
                    'method' => ['No cash register is open for this branch — open a shift before refunding in cash.'],
                ]);
            }

            $data['cash_drawer_id'] = $openDrawer->id;
        }

        $refund = $this->repository->create($data);

        $totalRefunded = $alreadyRefunded + $data['amount'];
        $fullyRefunded = $totalRefunded >= $payment->amount;

        $payment->update(['status' => $fullyRefunded ? 'refunded' : 'partially_refunded']);

        if ($fullyRefunded) {
            Order::updateStatus($payment->order_id, 'refunded', $data['staff_id'] ?? null);
        }

        return $refund;
    }
}
