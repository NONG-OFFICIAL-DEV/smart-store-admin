<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMenuRequest;
use App\Http\Requests\UpdateMenuRequest;
use App\Http\Resources\MenuResource;
use App\Models\Menu;
use App\Services\MenuService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    use ApiResponse;

    public function __construct(private MenuService $menus)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->menus->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage',
        ]));

        return $this->success(
            MenuResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreMenuRequest $request): JsonResponse
    {
        $menu = $this->menus->create($request->validated(), $request);

        return $this->created(new MenuResource($menu), 'Menu created successfully.');
    }

    public function show(Menu $menu): JsonResponse
    {
        return $this->success(new MenuResource($menu->load('branches')));
    }

    public function update(UpdateMenuRequest $request, Menu $menu): JsonResponse
    {
        $menu = $this->menus->update($menu, $request->validated());

        return $this->success(new MenuResource($menu), 'Menu updated successfully.');
    }

    public function destroy(Menu $menu): JsonResponse
    {
        $this->menus->delete($menu);

        return $this->noContent('Menu deleted successfully.');
    }

    /**
     * POST /menus/{menu}/branches/sync
     */
    public function syncBranches(Request $request, Menu $menu): JsonResponse
    {
        $request->validate([
            'branch_id' => ['present', 'array'],
            'branch_id.*' => ['uuid', 'exists:branches,id'],
        ]);

        $menu = $this->menus->syncBranches($menu, $request->branch_id);

        return $this->success(new MenuResource($menu), 'Menu branches updated successfully.');
    }
}
