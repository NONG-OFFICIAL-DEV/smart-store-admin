<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Menu;
use App\Services\CategoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use ApiResponse;

    public function __construct(private CategoryService $categories)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->categories->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage',
        ]));

        return $this->success(
            CategoryResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tenantIds = $validated['tenant_ids'] ?? [];
        $businessTypeIds = $validated['business_type_ids'] ?? [];
        unset($validated['tenant_ids'], $validated['business_type_ids']);

        $category = $this->categories->create($validated, $tenantIds, $businessTypeIds);

        return $this->created(new CategoryResource($category), 'Category created successfully.');
    }

    public function show(Category $category): JsonResponse
    {
        return $this->success(new CategoryResource($category->load(['tenants', 'businessTypes'])));
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $validated = $request->validated();
        $tenantIds = $validated['tenant_ids'] ?? $category->tenants->pluck('id')->all();
        $businessTypeIds = $validated['business_type_ids'] ?? $category->businessTypes->pluck('id')->all();
        unset($validated['tenant_ids'], $validated['business_type_ids']);

        $category = $this->categories->update($category, $validated, $tenantIds, $businessTypeIds);

        return $this->success(new CategoryResource($category), 'Category updated successfully.');
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->categories->delete($category);

        return $this->noContent('Category deleted successfully.');
    }

    /**
     * GET /menus/{menu}/categories — see CategoryService::byMenu() for why
     * this is reinterpreted through the menu's tenant rather than a direct
     * (never-existent) menu_id relationship.
     */
    public function byMenu(Menu $menu): JsonResponse
    {
        return $this->success(CategoryResource::collection($this->categories->byMenu($menu)));
    }
}
