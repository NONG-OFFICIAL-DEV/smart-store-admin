<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryTransactionResource;
use App\Models\Ingredient;
use App\Models\InventoryTransaction;
use App\Services\InventoryTransactionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryTransactionController extends Controller
{
    use ApiResponse;

    public function __construct(private InventoryTransactionService $transactions)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->transactions->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'branch_id', 'ingredient_id', 'transaction_type',
        ]));

        return $this->paginated($paginator);
    }

    public function byIngredient(Request $request, Ingredient $ingredient): JsonResponse
    {
        $paginator = $this->transactions->byIngredient($ingredient->id, $request->only(['search', 'sortBy', 'sortDesc', 'perPage']));

        return $this->paginated($paginator);
    }

    public function show(InventoryTransaction $inventory_transaction): JsonResponse
    {
        return $this->success(new InventoryTransactionResource($inventory_transaction));
    }

    private function paginated($paginator): JsonResponse
    {
        return $this->success(
            InventoryTransactionResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }
}
