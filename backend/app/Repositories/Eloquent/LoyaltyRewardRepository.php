<?php

namespace App\Repositories\Eloquent;

use App\Models\LoyaltyReward;
use App\Repositories\Contracts\LoyaltyRewardRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class LoyaltyRewardRepository extends BaseRepository implements LoyaltyRewardRepositoryInterface
{
    protected array $searchable = ['name'];

    public function __construct(LoyaltyReward $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return $this->model->newQuery()->with(['branches', 'freeProduct']);
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['branch_id'])) {
            // No branch rows at all = "every branch" — always included
            // alongside whichever specific branch is being asked about.
            $branchId = $filters['branch_id'];
            $query->where(
                fn (Builder $q) => $q->whereDoesntHave('branches')
                    ->orWhereHas('branches', fn (Builder $q2) => $q2->where('branches.id', $branchId))
            );
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', $filters['is_active']);
        }
    }
}
