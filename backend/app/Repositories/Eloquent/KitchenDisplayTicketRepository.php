<?php

namespace App\Repositories\Eloquent;

use App\Models\KitchenDisplayTicket;
use App\Repositories\Contracts\KitchenDisplayTicketRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class KitchenDisplayTicketRepository extends BaseRepository implements KitchenDisplayTicketRepositoryInterface
{
    public function __construct(KitchenDisplayTicket $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['station'])) {
            $query->where('station', $filters['station']);
        }
    }
}
