<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected array $searchable = ['first_name', 'last_name', 'email', 'phone'];

    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return parent::query()->with(['ownedTenant', 'staff.role', 'staff.branch']);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('verified', $filters) && $filters['verified'] !== null && $filters['verified'] !== '') {
            filter_var($filters['verified'], FILTER_VALIDATE_BOOLEAN)
                ? $query->whereNotNull('email_verified_at')
                : $query->whereNull('email_verified_at');
        }
    }
}
