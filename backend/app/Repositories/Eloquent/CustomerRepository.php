<?php

namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;

class CustomerRepository extends BaseRepository implements CustomerRepositoryInterface
{
    protected array $searchable = ['first_name', 'last_name', 'email', 'phone'];

    public function __construct(Customer $model)
    {
        parent::__construct($model);
    }
}
