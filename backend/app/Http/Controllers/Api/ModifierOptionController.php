<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreModifierOptionRequest;
use App\Http\Requests\UpdateModifierOptionRequest;
use App\Http\Resources\ModifierOptionResource;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Services\ModifierOptionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModifierOptionController extends Controller
{
    use ApiResponse;

    public function __construct(private ModifierOptionService $options)
    {
    }

    public function index(Request $request, ModifierGroup $modifierGroup): JsonResponse
    {
        $paginator = $this->options->byGroup($modifierGroup, $request->only([
            'search', 'sortBy', 'sortDesc', 'perPage',
        ]));

        return $this->success(
            ModifierOptionResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreModifierOptionRequest $request, ModifierGroup $modifierGroup): JsonResponse
    {
        $option = $this->options->create($modifierGroup, $request->validated());

        return $this->created(new ModifierOptionResource($option), 'Modifier option created successfully.');
    }

    public function show(ModifierGroup $modifierGroup, ModifierOption $option): JsonResponse
    {
        if ($mismatch = $this->assertBelongsToGroup($modifierGroup, $option)) {
            return $mismatch;
        }

        return $this->success(new ModifierOptionResource($option));
    }

    public function update(UpdateModifierOptionRequest $request, ModifierGroup $modifierGroup, ModifierOption $option): JsonResponse
    {
        if ($mismatch = $this->assertBelongsToGroup($modifierGroup, $option)) {
            return $mismatch;
        }

        $option = $this->options->update($option, $request->validated());

        return $this->success(new ModifierOptionResource($option), 'Modifier option updated successfully.');
    }

    public function destroy(ModifierGroup $modifierGroup, ModifierOption $option): JsonResponse
    {
        if ($mismatch = $this->assertBelongsToGroup($modifierGroup, $option)) {
            return $mismatch;
        }

        $this->options->delete($option);

        return $this->noContent('Modifier option deleted successfully.');
    }

    /**
     * Both {modifierGroup} and {option} are bound independently by id —
     * Laravel never cross-checks that the option actually belongs to the
     * group named in the URL. Combined with modifier_options previously
     * having no tenant scope at all, this meant a same-tenant user could
     * reference one of their own groups alongside ANY other tenant's
     * option id and still act on it (TenantScope now blocks the
     * cross-tenant case at the binding level; this catches the
     * same-tenant "wrong group in the URL" case for correctness).
     */
    private function assertBelongsToGroup(ModifierGroup $modifierGroup, ModifierOption $option): ?JsonResponse
    {
        if ($option->group_id === $modifierGroup->id) {
            return null;
        }

        return $this->error('This option does not belong to the specified modifier group.', 404, [], 'MODIFIER_OPTION_GROUP_MISMATCH');
    }
}
