<?php

namespace App\Services;

use App\Models\Menu;
use App\Repositories\Contracts\MenuRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class MenuService extends BaseService
{
    public function __construct(
        MenuRepositoryInterface $repository,
        private TenantResolver $tenantResolver
    ) {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function create(array $data, Request $request): Menu
    {
        $tenantId = $this->tenantResolver->resolve($request);
        $data['tenant_id'] = $tenantId;

        // Only one default menu per tenant — was previously checked BEFORE
        // $tenantId got injected into $data, so it always ran
        // where('tenant_id', null), matching zero rows. Setting a new
        // default menu never actually unset the previous one.
        if (! empty($data['is_default'])) {
            Menu::where('tenant_id', $tenantId)->update(['is_default' => false]);
        }

        return $this->repository->create($data);
    }

    public function update(Menu $menu, array $data): Menu
    {
        if (! empty($data['is_default'])) {
            Menu::where('tenant_id', $menu->tenant_id)->where('id', '!=', $menu->id)->update(['is_default' => false]);
        }

        return $this->repository->update($menu, $data);
    }

    public function delete(Menu $menu): bool
    {
        return $this->repository->delete($menu);
    }

    // Full replace of this menu's branch assignments — previously dead
    // (routed to MenuController::syncBranches, which didn't exist).
    public function syncBranches(Menu $menu, array $branchIds): Menu
    {
        $menu->branches()->sync($branchIds);

        return $menu->load('branches');
    }
}
