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
    public function __construct(RefundRepositoryInterface $repository)
    {
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
