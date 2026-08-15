<?php

namespace App\Repositories\Eloquent;

use App\Models\Reservation;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class ReservationRepository extends BaseRepository implements ReservationRepositoryInterface
{
    protected array $searchable = ['customer_name', 'status'];

    public function __construct(Reservation $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return $this->model->newQuery()->with(['table', 'branch']);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['date'])) {
            $query->whereDate('reserved_at', $filters['date']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['table_id'])) {
            $query->where('table_id', $filters['table_id']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }
    }
}
