<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchMenuRequest;
use App\Http\Requests\UpdateBranchMenuRequest;
use App\Http\Resources\BranchMenuResource;
use App\Models\BranchMenu;
use App\Services\BranchMenuService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchMenuController extends Controller
{
    use ApiResponse;

    public function __construct(private BranchMenuService $branchMenus)
    {
    }

    /**
     * ?branch_id=uuid → all menus assigned to a branch
     * ?menu_id=uuid   → all branches that have this menu
     */
    public function index(Request $request): JsonResponse
    {
        $items = $this->branchMenus->list($request->only(['branch_id', 'menu_id']));

        return $this->success(BranchMenuResource::collection($items));
    }

    /**
     * Assigns a menu to one or more branches. A single branch_id (the only
     * shape the current UI sends) gets a flat single-record response; an
     * array of branch_ids gets the bulk created/skipped/errors summary —
     * previously always returned the bulk shape even for a single id,
     * which the frontend store then pushed whole into its flat list,
     * corrupting it with a nested array instead of a record.
     */
    public function store(StoreBranchMenuRequest $request): JsonResponse
    {
        $result = $this->branchMenus->assign($request->branch_id, $request->menu_id, $request->validated());

        if ($result['created']->count() === 1 && empty($result['skipped']) && empty($result['errors'])) {
            return $this->created(new BranchMenuResource($result['created']->first()), 'Menu assigned to branch successfully.');
        }

        return $this->success(
            BranchMenuResource::collection($result['created']),
            'Menu assignment processed.',
            201,
            meta: [
                'created' => $result['created']->count(),
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
            ]
        );
    }

    public function show(BranchMenu $branchMenu): JsonResponse
    {
        // Was `menu.categories.products` — see BranchMenuService::availableNow()
        // for why that 500s unconditionally (Category has no menu_id column).
        return $this->success(new BranchMenuResource($branchMenu->load(['branch', 'menu'])));
    }

    public function update(UpdateBranchMenuRequest $request, BranchMenu $branchMenu): JsonResponse
    {
        $branchMenu = $this->branchMenus->update($branchMenu, $request->validated());

        return $this->success(new BranchMenuResource($branchMenu), 'Branch menu updated successfully.');
    }

    public function destroy(BranchMenu $branchMenu): JsonResponse
    {
        $this->branchMenus->delete($branchMenu);

        return $this->noContent('Menu removed from branch.');
    }

    public function availableNow(string $branchId): JsonResponse
    {
        $available = $this->branchMenus->availableNow($branchId);

        return $this->success(BranchMenuResource::collection($available), meta: [
            'time' => now()->format('H:i'),
            'day' => now()->dayOfWeek,
        ]);
    }

    public function unassign(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => ['required', 'uuid'],
            'menu_id' => ['required', 'uuid'],
        ]);

        $this->branchMenus->unassign($request->branch_id, $request->menu_id);

        return $this->noContent('Menu removed from branch.');
    }
}
