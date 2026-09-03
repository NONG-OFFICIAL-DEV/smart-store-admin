<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Menu;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CategoryService extends BaseService
{
    public function __construct(CategoryRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    /**
     * Categories have no tenant_id at all (see CLAUDE.md) — sharing is via
     * the category_tenant pivot, kept in sync with $tenantIds on every
     * write, matching the old controller's ->sync() call exactly.
     *
     * A non-super-admin can only ever create their OWN custom category —
     * never system-wide (is_system/business_type_ids are super-admin-only
     * levers) and never shared with any tenant but themselves, regardless
     * of what the request body actually contained.
     */
    public function create(array $data, array $tenantIds, array $businessTypeIds = []): Category
    {
        $user = auth()->user();

        if (!$user->is_super_admin) {
            $data['is_system'] = false;
            $businessTypeIds = [];
            $tenantIds = array_filter([$this->currentTenantId($user)]);
        }

        $data['sort_order'] = $this->resolveInsertOrder($data);

        return DB::transaction(function () use ($data, $tenantIds, $businessTypeIds) {
            $category = $this->repository->create($data);
            $category->tenants()->sync($tenantIds);
            $category->businessTypes()->sync($businessTypeIds);

            return $category->load(['tenants', 'businessTypes']);
        });
    }

    public function update(Category $category, array $data, array $tenantIds, array $businessTypeIds = []): Category
    {
        $user = auth()->user();

        if ($category->is_system && !$user->is_super_admin) {
            abort(403, 'Only a super admin can modify a system category.');
        }

        if (!$user->is_super_admin) {
            unset($data['is_system']);
            $businessTypeIds = $category->businessTypes->pluck('id')->all();
            $tenantIds = array_filter([$this->currentTenantId($user)]);
        }

        return DB::transaction(function () use ($category, $data, $tenantIds, $businessTypeIds) {
            $this->resolveUpdateOrder($category, $data);

            $category = $this->repository->update($category, $data);
            $category->tenants()->sync($tenantIds);
            $category->businessTypes()->sync($businessTypeIds);

            return $category->load(['tenants', 'businessTypes']);
        });
    }

    public function delete(Category $category): bool
    {
        if ($category->is_system && !auth()->user()->is_super_admin) {
            abort(403, 'Only a super admin can delete a system category.');
        }

        return $this->repository->delete($category);
    }

    private function currentTenantId($user): ?string
    {
        return $user->ownedTenant?->id
            ?? $user->staff()->withoutGlobalScopes()->first()?->tenant_id;
    }

    /**
     * Categories relevant to this menu's tenant — categories have no
     * direct menu relationship (see CLAUDE.md: the menu_id column/relation
     * on Category never existed at the DB level; category_tenant is the
     * real, working link), so this reinterprets the route through the
     * menu's own tenant rather than leaving it a guaranteed 500.
     */
    public function byMenu(Menu $menu): Collection
    {
        return Category::whereHas('tenants', fn ($q) => $q->where('tenant_id', $menu->tenant_id))
            ->orderBy('sort_order')
            ->get();
    }

    private function resolveInsertOrder(array $data): int
    {
        $parentId = $data['parent_id'] ?? null;
        $requested = $data['sort_order'] ?? null;

        if (is_null($requested)) {
            return (int) $this->siblingsOf($parentId)->max('sort_order') + 1;
        }

        $this->siblingsOf($parentId)
            ->where('sort_order', '>=', $requested)
            ->increment('sort_order');

        return $requested;
    }

    private function resolveUpdateOrder(Category $category, array $data): void
    {
        if (! isset($data['sort_order']) || (int) $data['sort_order'] === (int) $category->sort_order) {
            return;
        }

        $parentId = $data['parent_id'] ?? $category->parent_id;
        $oldOrder = $category->sort_order;
        $newOrder = (int) $data['sort_order'];

        if ($newOrder > $oldOrder) {
            $this->siblingsOf($parentId)
                ->whereBetween('sort_order', [$oldOrder + 1, $newOrder])
                ->where('id', '!=', $category->id)
                ->decrement('sort_order');
        } else {
            $this->siblingsOf($parentId)
                ->whereBetween('sort_order', [$newOrder, $oldOrder - 1])
                ->where('id', '!=', $category->id)
                ->increment('sort_order');
        }
    }

    /**
     * Categories sharing the same parent — a bare where('parent_id', null)
     * matches zero rows in SQL (= NULL is never true), so every top-level
     * (parentless) category's insert/reorder logic silently no-oped: new
     * top-level categories always computed max()+1 against an empty result
     * (always landing on sort_order=1), and reordering never shifted any
     * other top-level sibling.
     */
    private function siblingsOf(?string $parentId): Builder
    {
        return is_null($parentId)
            ? Category::whereNull('parent_id')
            : Category::where('parent_id', $parentId);
    }
}
