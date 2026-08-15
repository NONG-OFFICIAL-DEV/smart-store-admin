<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface PlanRepositoryInterface extends RepositoryInterface
{
    public function allOrdered(bool $activeOnly = false): Collection;
}
