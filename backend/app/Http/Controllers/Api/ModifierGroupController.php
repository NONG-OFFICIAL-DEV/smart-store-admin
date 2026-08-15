<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreModifierGroupRequest;
use App\Http\Requests\UpdateModifierGroupRequest;
use App\Http\Resources\ModifierGroupResource;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Services\ModifierGroupService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModifierGroupController extends Controller
{
    use ApiResponse;

    public function __construct(private ModifierGroupService $modifierGroups)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->modifierGroups->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage',
        ]));

        return $this->success(
            ModifierGroupResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function byProduct(Product $product): JsonResponse
    {
        return $this->success(ModifierGroupResource::collection($product->modifierGroups));
    }

    public function store(StoreModifierGroupRequest $request): JsonResponse
    {
        $modifierGroup = $this->modifierGroups->create($request->validated(), $request);

        return $this->created(new ModifierGroupResource($modifierGroup), 'Modifier group created successfully.');
    }

    public function show(ModifierGroup $modifier_group): JsonResponse
    {
        return $this->success(new ModifierGroupResource($modifier_group->load('options')));
    }

    public function update(UpdateModifierGroupRequest $request, ModifierGroup $modifier_group): JsonResponse
    {
        $modifier_group = $this->modifierGroups->update($modifier_group, $request->validated());

        return $this->success(new ModifierGroupResource($modifier_group), 'Modifier group updated successfully.');
    }

    public function destroy(ModifierGroup $modifier_group): JsonResponse
    {
        $this->modifierGroups->delete($modifier_group);

        return $this->noContent('Modifier group deleted successfully.');
    }
}
