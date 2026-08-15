<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface BusinessTypeRepositoryInterface extends RepositoryInterface
{
    public function allOrdered(): Collection;
}
