<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 10), 100);
        $query = ProductVariant::query();
        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%");
        }
        $query->orderBy($request->get('sort_by', 'sort_order'), $request->get('sort_order', 'desc'));
        $items = $query->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Product variants retrieved successfully.',
            'data'    => $items,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        return ProductVariant::store($request);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        return ProductVariant::store($request, $id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $tenant = ProductVariant::findOrFail($id);
        $tenant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product Variant deleted',
        ]);
    }

    /**
     * Drag-reorder a single product's sizes. Scoped by product_id so an id
     * belonging to a different (even cross-tenant, via TenantScope) product
     * can never be reordered through this product's own endpoint.
     */
    public function reorder(Request $request, Product $product)
    {
        $validated = $request->validate([
            'variant_ids' => ['required', 'array', 'min:1'],
            'variant_ids.*' => ['required', 'uuid'],
        ]);

        foreach ($validated['variant_ids'] as $index => $id) {
            ProductVariant::where('id', $id)
                ->where('product_id', $product->id)
                ->update(['sort_order' => $index]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sizes reordered successfully.',
            'data' => $product->variants()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }
}
