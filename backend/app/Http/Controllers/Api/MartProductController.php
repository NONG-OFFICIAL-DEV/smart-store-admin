<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class MartProductController extends Controller
{
    /**
     * GET /api/v1/mart/products
     * All mart products with current stock_quantity + active units
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = Product::with([
            'activeUnits' => function ($q) {
                $q->orderByDesc('is_base_unit'); // base unit first (nice UX)
            }
        ])

            // 🔍 SEARCH
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;

                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })

            // 📂 CATEGORY
            ->when(
                $request->filled('category_id'),
                fn($q) =>
                $q->where('category_id', $request->category_id)
            )
            // 📦 STOCK FILTER
            ->when($request->stock_filter, function ($q, $filter) {
                return match ($filter) {
                    'out' => $q->where('stock_quantity', 0),

                    'low' => $q->where('stock_quantity', '>', 0)
                        ->whereColumn('stock_quantity', '<=', 'reorder_level'),

                    'ok'  => $q->whereColumn('stock_quantity', '>', 'reorder_level'),

                    default => $q
                };
            })

            // 🔽 SORT
            ->when($request->filled('sort_by'), function ($q) use ($request) {
                $sort = $request->sort_by;
                $direction = $request->get('sort_dir', 'asc');

                $allowed = ['name', 'base_price', 'created_at'];

                if (in_array($sort, $allowed)) {
                    $q->orderBy($sort, $direction);
                }
            }, fn($q) => $q->orderBy('name'));

        // ✅ HANDLE pagination OR get all
        if ($perPage === 'all' || (int)$perPage === -1) {
            $collection = $query->get();

            $data = $collection->map(fn($p) => $this->transformProduct($p));

            return response()->json([
                'data' => $data,
                'meta' => null
            ]);
        }

        // ✅ NORMAL PAGINATION
        $perPage = min(max((int)$perPage, 1), 100);

        $products = $query->paginate($perPage);

        $products->getCollection()->transform(
            fn($p) => $this->transformProduct($p)
        );

        return response()->json($products);
    }

    private function transformProduct($p)
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'barcode' => $p->barcode,
            'image_url' => $p->image_url,
            'unit' => $p->unit,
            'stock_quantity' => (float) $p->stock_quantity,
            'reorder_level' => $p->reorder_level !== null ? (float) $p->reorder_level : null,
            'cost_price' => (float) $p->cost_price,
            'base_price' => (float) $p->base_price,
            'retail_price' => (float) $p->retail_price,
            'wholesale_price' => (float) $p->wholesale_price,
            'is_available' => (bool) $p->is_available,
            'is_featured' => (bool) $p->is_featured,

            'active_units' => $p->activeUnits->map(fn($u) => [
                'id' => $u->id,
                'unit_name' => $u->unit_name,
                'unit_label' => $u->unit_label,
                'qty_per_base' => (float) $u->qty_per_base,
                'cost_price' => (float) $u->cost_price,
                'retail_price' => (float) $u->retail_price,
                'wholesale_price' => (float) $u->wholesale_price,
                'is_base_unit' => (bool) $u->is_base_unit,
                'barcode' => $u->barcode,
            ]),
        ];
    }
    /**
     * GET /api/v1/mart/products/{id}
     * Single product with full detail
     */
    public function show(Request $request, $id)
    {
        $tenantId = auth()->user()->staff->tenant_id;

        $product = Product::with(['activeUnits', 'category'])
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'id'              => $product->id,
                'name'            => $product->name,
                'sku'             => $product->sku,
                'image_url'       => $product->image_url,
                'unit'            => $product->unit,
                'stock_quantity'  => (float) $product->stock_quantity,
                'reorder_level'   => $product->reorder_level !== null ? (float) $product->reorder_level : null,
                'cost_price'      => (float) $product->cost_price,
                'retail_price'    => (float) $product->retail_price,
                'wholesale_price' => (float) $product->wholesale_price,
                'category'        => $product->category?->only(['id', 'name']),
                'active_units'    => $product->activeUnits->map(fn($u) => [
                    'id'              => $u->id,
                    'unit_name'       => $u->unit_name,
                    'unit_label'      => $u->unit_label,
                    'qty_per_base'    => (float) $u->qty_per_base,
                    'cost_price'      => (float) $u->cost_price,
                    'retail_price'    => (float) $u->retail_price,
                    'wholesale_price' => (float) $u->wholesale_price,
                    'is_base_unit'    => (bool)  $u->is_base_unit,
                    'barcode'         => $u->barcode,
                ]),
            ]
        ]);
    }
}
