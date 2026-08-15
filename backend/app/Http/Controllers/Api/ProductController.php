<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttachProductModifierGroupsRequest;
use App\Http\Requests\ScanProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductService;
use App\Services\TenantResolver;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ProductService $products,
        private TenantResolver $tenantResolver,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->products->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'categories', 'is_available',
        ]));

        return $this->paginated($paginator);
    }

    public function byCategory(Request $request, Category $category): JsonResponse
    {
        $paginator = $this->products->byCategory($category->id, $request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'is_available',
        ]));

        return $this->paginated($paginator);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $tenantId = $this->tenantResolver->resolve($request);
        $product = $this->products->create($request->validated(), $tenantId, $request->file('image'));

        return $this->created($product, 'Product created successfully.');
    }

    public function show(Product $product): JsonResponse
    {
        return $this->success($this->products->detail($product));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        // Matches the original ProductControllerV2 exactly: resolve()
        // (not resolveOrNull()) — a super admin must still supply
        // tenant_id even for a trivial edit, same as before this
        // migration. Not changing that behavior here.
        $tenantId = $this->tenantResolver->resolve($request);
        $product = $this->products->update(
            $product,
            $request->validated(),
            $tenantId,
            $request->user()->is_super_admin,
            $request->file('image'),
        );

        return $this->success($product, 'Product updated successfully.');
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->products->delete($product);

        return $this->noContent('Product removed successfully.');
    }

    public function attachModifierGroups(AttachProductModifierGroupsRequest $request, Product $product): JsonResponse
    {
        $product = $this->products->attachModifierGroups($product, $request->validated('modifier_group_ids'));

        return $this->success($product, 'Modifier groups linked successfully.');
    }

    // Barcode scan lookup — routed under mart POS sale-taking, no frontend
    // consumer yet (no hardware scanner integration built in the UI).
    public function scan(ScanProductRequest $request): JsonResponse
    {
        try {
            $product = $this->products->findByBarcode($request->validated('barcode'));
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), $e->getMessage() === 'Out of stock' ? 422 : 404, $e->errors(), 'SCAN_FAILED');
        }

        return $this->success($product);
    }

    private function paginated($paginator): JsonResponse
    {
        return $this->success(
            $paginator->items(),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }
}
