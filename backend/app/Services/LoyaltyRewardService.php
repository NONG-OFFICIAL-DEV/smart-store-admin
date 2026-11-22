<?php

namespace App\Services;

use App\Models\LoyaltyReward;
use App\Repositories\Contracts\LoyaltyRewardRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class LoyaltyRewardService extends BaseService
{
    public function __construct(
        LoyaltyRewardRepositoryInterface $repository,
        private TenantResolver $tenantResolver
    ) {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters, (int) ($filters['perPage'] ?? 15));
    }

    public function create(array $data, Request $request): LoyaltyReward
    {
        $data['tenant_id'] = $this->tenantResolver->resolve($request);
        $branchIds = $data['branch_ids'] ?? [];
        unset($data['branch_ids']);

        $reward = $this->repository->create($data);
        $reward->branches()->sync($branchIds);

        return $reward->load(['branches', 'freeProduct']);
    }

    public function update(LoyaltyReward $reward, array $data): LoyaltyReward
    {
        $branchIds = $data['branch_ids'] ?? null;
        unset($data['branch_ids']);

        $reward = $this->repository->update($reward, $data);

        if ($branchIds !== null) {
            $reward->branches()->sync($branchIds);
        }

        return $reward->load(['branches', 'freeProduct']);
    }

    public function delete(LoyaltyReward $reward): bool
    {
        return $this->repository->delete($reward);
    }

    /**
     * What a customer-facing screen (admin preview or the Mini App) can
     * actually redeem right now: active, within-date rewards available at
     * their branch (or tenant-wide), cheapest first so "almost there"
     * rewards surface before aspirational ones.
     */
    public function availableFor(string $tenantId, ?string $branchId = null)
    {
        $query = LoyaltyReward::availableNow()->where('tenant_id', $tenantId)->with(['branches', 'freeProduct']);

        if ($branchId) {
            $query->where(
                fn (Builder $q) => $q->whereDoesntHave('branches')
                    ->orWhereHas('branches', fn (Builder $q2) => $q2->where('branches.id', $branchId))
            );
        }

        return $query->orderBy('points_cost')->get();
    }
}
