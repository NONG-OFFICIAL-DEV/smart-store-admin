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

    // Without this, the frontend (Kitchen Display / Mobile Orders Carousel)
    // had to follow up with one GET /orders/{id} PER ticket just to show the
    // order number, table, and items on each card — dozens of extra requests
    // on a single page load. Eager-loading here and nesting the data in
    // KitchenDisplayTicketResource turns that into zero extra requests.
    public function query(): Builder
    {
        return parent::query()->with(['order.diningTable', 'order.items.modifiers']);
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
