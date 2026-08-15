<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchMenu;
use App\Models\Menu;
use App\Repositories\Contracts\BranchMenuRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class BranchMenuService extends BaseService
{
    public function __construct(BranchMenuRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function list(array $filters): Collection
    {
        $query = $this->repository->query();

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['menu_id'])) {
            $query->where('menu_id', $filters['menu_id']);
        }

        return $query->orderBy('sort_order')->get();
    }

    /**
     * Assigns a menu to one or more branches. $branchIds is always an array
     * here (the FormRequest normalizes a single id into a one-item array) —
     * the caller decides whether to present the result as a single record
     * or a bulk summary based on count($result['created']).
     *
     * @return array{created: SupportCollection<int, BranchMenu>, skipped: string[], errors: string[]}
     */
    public function assign(array $branchIds, string $menuId, array $data): array
    {
        $menu = Menu::findOrFail($menuId);

        $created = new SupportCollection();
        $skipped = [];
        $errors = [];

        foreach ($branchIds as $branchId) {
            $branch = Branch::find($branchId);

            if (! $branch) {
                $errors[] = "Branch {$branchId} not found";

                continue;
            }

            // Branch and menu must belong to the same tenant.
            if ($branch->tenant_id !== $menu->tenant_id) {
                $errors[] = "Branch {$branch->name} does not belong to the same business";

                continue;
            }

            if (BranchMenu::where('branch_id', $branchId)->where('menu_id', $menuId)->exists()) {
                $skipped[] = $branch->name;

                continue;
            }

            $record = $this->repository->create([
                'branch_id' => $branchId,
                'menu_id' => $menuId,
                'available_from' => $data['available_from'] ?? null,
                'available_until' => $data['available_until'] ?? null,
                'days_of_week' => $data['days_of_week'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $created->push($record->load(['branch', 'menu']));
        }

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }

    public function update(BranchMenu $branchMenu, array $data): BranchMenu
    {
        $branchMenu = $this->repository->update($branchMenu, $data);

        return $branchMenu->load(['branch', 'menu']);
    }

    public function delete(BranchMenu $branchMenu): bool
    {
        return $this->repository->delete($branchMenu);
    }

    public function unassign(string $branchId, string $menuId): void
    {
        BranchMenu::where('branch_id', $branchId)->where('menu_id', $menuId)->firstOrFail()->delete();
    }

    public function availableNow(string $branchId): Collection
    {
        // Was `with(['menu.categories.products'])` — Category::$fillable
        // includes menu_id but the categories table has no such column
        // (CLAUDE.md: tenant linkage for categories goes through the
        // category_tenant pivot instead), so Menu::categories() 500s
        // unconditionally. This endpoint has no live caller today (dead
        // in the frontend), so nothing currently depends on the deep
        // load — dropped down to what BranchMenuResource actually uses.
        return BranchMenu::with(['menu'])
            ->where('branch_id', $branchId)
            ->get()
            ->filter(fn (BranchMenu $assignment) => $assignment->isAvailableNow())
            ->values();
    }
}
