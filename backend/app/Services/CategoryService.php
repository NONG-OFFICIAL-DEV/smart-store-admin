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
     */
    public function create(array $data, array $tenantIds): Category
    {
        $data['sort_order'] = $this->resolveInsertOrder($data);

        return DB::transaction(function () use ($data, $tenantIds) {
            $category = $this->repository->create($data);
            $category->tenants()->sync($tenantIds);

            return $category->load('tenants');
        });
    }

    public function update(Category $category, array $data, array $tenantIds): Category
    {
        return DB::transaction(function () use ($category, $data, $tenantIds) {
            $this->resolveUpdateOrder($category, $data);

            $category = $this->repository->update($category, $data);
            $category->tenants()->sync($tenantIds);

            return $category->load('tenants');
        });
    }

    public function delete(Category $category): bool
    {
        return $this->repository->delete($category);
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
