<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIngredientRequest;
use App\Http\Requests\UpdateIngredientRequest;
use App\Http\Resources\IngredientResource;
use App\Models\Ingredient;
use App\Services\IngredientService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    use ApiResponse;

    public function __construct(private IngredientService $ingredients)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->ingredients->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'category', 'is_active', 'low_stock',
        ]));

        return $this->success(
            IngredientResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreIngredientRequest $request): JsonResponse
    {
        $ingredient = $this->ingredients->create($request->validated(), $request);

        return $this->created(new IngredientResource($ingredient), 'Ingredient created successfully.');
    }

    public function show(Ingredient $ingredient): JsonResponse
    {
        return $this->success(new IngredientResource($ingredient));
    }

    public function update(UpdateIngredientRequest $request, Ingredient $ingredient): JsonResponse
    {
        $ingredient = $this->ingredients->update($ingredient, $request->validated());

        return $this->success(new IngredientResource($ingredient), 'Ingredient updated successfully.');
    }

    public function destroy(Ingredient $ingredient): JsonResponse
    {
        $this->ingredients->delete($ingredient);

        return $this->noContent('Ingredient deleted successfully.');
    }
}
