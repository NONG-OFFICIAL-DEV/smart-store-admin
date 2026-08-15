<?php

namespace App\Repositories\Eloquent;

use App\Models\Refund;
use App\Repositories\Contracts\RefundRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class RefundRepository extends BaseRepository implements RefundRepositoryInterface
{
    protected array $searchable = ['reason', 'status', 'method'];

    public function __construct(Refund $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['payment_id'])) {
            $query->where('payment_id', $filters['payment_id']);
        }

        if (! empty($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
    }
}
