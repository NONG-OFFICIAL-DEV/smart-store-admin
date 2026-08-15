<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustInventoryStockRequest;
use App\Http\Requests\StoreInventoryStockRequest;
use App\Http\Requests\UpdateInventoryStockRequest;
use App\Http\Resources\InventoryStockResource;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\InventoryStock;
use App\Services\InventoryStockService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryStockController extends Controller
{
    use ApiResponse;

    public function __construct(private InventoryStockService $stock)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->stock->list($request->only([
            'sortBy', 'sortDesc', 'perPage', 'branch_id', 'ingredient_id',
        ]));

        return $this->paginated($paginator);
    }

    public function byBranch(Request $request, Branch $branch): JsonResponse
    {
        $paginator = $this->stock->byBranch($branch->id, $request->only(['sortBy', 'sortDesc', 'perPage']));

        return $this->paginated($paginator);
    }

    public function byIngredient(Request $request, Ingredient $ingredient): JsonResponse
    {
        $paginator = $this->stock->byIngredient($ingredient->id, $request->only(['sortBy', 'sortDesc', 'perPage']));

        return $this->paginated($paginator);
    }

    public function store(StoreInventoryStockRequest $request): JsonResponse
    {
        $stock = $this->stock->create($request->validated());

        return $this->created(new InventoryStockResource($stock), 'Inventory stock created successfully.');
    }

    public function adjust(AdjustInventoryStockRequest $request): JsonResponse
    {
        $data = $request->validated();
        $stock = $this->stock->adjust(
            $data['branch_id'],
            $data['ingredient_id'],
            $data['quantity'],
            $data['type'],
            $data['staff_id'] ?? null,
            $data['notes'] ?? null,
        );

        return $this->success(new InventoryStockResource($stock), 'Inventory stock adjusted successfully.');
    }

    public function show(InventoryStock $inventory_stock): JsonResponse
    {
        return $this->success(new InventoryStockResource($inventory_stock));
    }

    public function update(UpdateInventoryStockRequest $request, InventoryStock $inventory_stock): JsonResponse
    {
        $inventory_stock = $this->stock->update($inventory_stock, $request->validated());

        return $this->success(new InventoryStockResource($inventory_stock), 'Inventory stock updated successfully.');
    }

    public function destroy(InventoryStock $inventory_stock): JsonResponse
    {
        $this->stock->delete($inventory_stock);

        return $this->noContent('Inventory stock deleted successfully.');
    }

    private function paginated($paginator): JsonResponse
    {
        return $this->success(
            InventoryStockResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }
}
