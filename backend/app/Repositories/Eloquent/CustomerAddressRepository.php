<?php

namespace App\Repositories\Eloquent;

use App\Models\CustomerAddress;
use App\Repositories\Contracts\CustomerAddressRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class CustomerAddressRepository extends BaseRepository implements CustomerAddressRepositoryInterface
{
    protected array $searchable = ['label', 'address_line1', 'address_line2', 'city', 'state', 'country'];

    public function __construct(CustomerAddress $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
    }

    // customer_addresses has no created_at column — BaseRepository's
    // default sort (latest()) would 500 on any listing with no explicit
    // sort requested.
    protected function applySort(Builder $query, ?string $sortBy, bool|string $sortDesc = false): void
    {
        parent::applySort($query, $sortBy ?: 'label', $sortDesc);
    }
}
