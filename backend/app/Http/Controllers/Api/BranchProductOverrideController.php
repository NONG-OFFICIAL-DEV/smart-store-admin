<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchProductOverrideRequest;
use App\Http\Requests\StoreProductBranchOverrideRequest;
use App\Http\Requests\UpdateBranchProductOverrideRequest;
use App\Http\Resources\BranchProductOverrideResource;
use App\Models\BranchProductOverride;
use App\Models\Product;
use App\Services\BranchProductOverrideService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchProductOverrideController extends Controller
{
    use ApiResponse;

    public function __construct(private BranchProductOverrideService $overrides)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->overrides->list($request->only([
            'sortBy', 'sortDesc', 'perPage', 'branch_id', 'product_id',
        ]));

        return $this->success(
            BranchProductOverrideResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreBranchProductOverrideRequest $request): JsonResponse
    {
        $override = $this->overrides->upsert($request->validated());

        return $this->created(new BranchProductOverrideResource($override), 'Branch product override saved successfully.');
    }

    /**
     * POST /products/{product}/branch-override
     */
    public function storeForProduct(StoreProductBranchOverrideRequest $request, Product $product): JsonResponse
    {
        $override = $this->overrides->upsert(array_merge($request->validated(), ['product_id' => $product->id]));

        return $this->created(new BranchProductOverrideResource($override), 'Branch product override saved successfully.');
    }

    public function show(BranchProductOverride $branch_product_override): JsonResponse
    {
        return $this->success(new BranchProductOverrideResource($branch_product_override->load(['branch', 'product'])));
    }

    public function update(UpdateBranchProductOverrideRequest $request, BranchProductOverride $branch_product_override): JsonResponse
    {
        $branch_product_override = $this->overrides->update($branch_product_override, $request->validated());

        return $this->success(new BranchProductOverrideResource($branch_product_override), 'Branch product override updated successfully.');
    }

    public function destroy(BranchProductOverride $branch_product_override): JsonResponse
    {
        $this->overrides->delete($branch_product_override);

        return $this->noContent('Branch product override deleted successfully.');
    }
}
