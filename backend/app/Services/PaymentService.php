<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentService extends BaseService
{
    public function __construct(PaymentRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function byOrder(Order $order, array $filters): LengthAwarePaginator
    {
        return $this->list(array_merge($filters, ['order_id' => $order->id]));
    }

    public function create(array $data): Payment
    {
        $order = Order::findOrFail($data['order_id']);

        // branch_id is always derived from the order, never trusted from
        // the client — the order is the source of truth for which branch
        // a payment belongs to.
        $data['branch_id'] = $order->branch_id;
        $data['status'] = $data['status'] ?? 'completed';

        if ($data['status'] === 'completed' && empty($data['paid_at'])) {
            $data['paid_at'] = now();
        }

        $payment = $this->repository->create($data);

        if ($payment->status === 'completed') {
            $this->completeOrderIfFullyPaid($order);
        }

        return $payment;
    }

    public function update(Payment $payment, array $data): Payment
    {
        if (($data['status'] ?? null) === 'completed' && empty($data['paid_at']) && ! $payment->paid_at) {
            $data['paid_at'] = now();
        }

        $payment = $this->repository->update($payment, $data);

        if ($payment->status === 'completed') {
            $this->completeOrderIfFullyPaid($payment->order);
        }

        return $payment;
    }

    public function delete(Payment $payment): bool
    {
        return $this->repository->delete($payment);
    }

    /**
     * orders.amount_due doesn't exist as a column (the old, dead
     * Payment::store() checked it anyway — always true, since a missing
     * attribute resolves to null and `null <= 0` is true in PHP — so this
     * side effect never correctly fired). Computed properly here: sum this
     * order's completed payments against its total_amount.
     */
    private function completeOrderIfFullyPaid(Order $order): void
    {
        if ($order->status === 'completed') {
            return;
        }

        $paid = $order->payments()->where('status', 'completed')->sum('amount');

        if ($paid >= $order->total_amount) {
            Order::updateStatus($order->id, 'completed', $order->staff_id);
        }
    }
}
