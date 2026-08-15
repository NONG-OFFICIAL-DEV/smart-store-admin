<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

interface NotificationRepositoryInterface extends RepositoryInterface
{
    public function queryVisibleTo(User $user): Builder;
}
