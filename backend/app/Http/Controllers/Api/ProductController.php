<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15); // default 15, override via ?per_page=25
        $perPage = min($perPage, 100); // cap at 100 to prevent abuse

        $query = Product::query();

        // Optional: global search
        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Optional: sorting
        $sortBy    = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $items = $query->paginate($perPage);

        return response()->json($items);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        return Product::store($request);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::where('id', $id)
            ->with([
                // Category info
                'category:id,name,icon,color',

                // Variants (ordered)
                'variants' => fn($q) => $q->orderBy('sort_order')->orderBy('name'),

                // Modifier groups linked via pivot, with their options
                'modifierGroups' => fn($q) => $q
                    ->orderBy('product_modifier_groups.sort_order')
                    ->with([
                        'options' => fn($q) => $q
                            ->orderBy('sort_order')
                            ->orderBy('name'),
                    ]),

                // Branch overrides (if you want to show per-branch pricing)
                'branchOverrides' => fn($q) => $q
                    ->with('branch:id,name'),
            ])
            ->firstOrFail();

        return response()->json([
            'data' => $product,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        return Product::store($request, $id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $record = Product::find($id);

        if (!$record) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product removed'
        ]);
    }

    public function attachModifierGroups(Request $request, Product $product)
    {
        $request->validate([
            'modifier_group_ids'   => 'required|array|min:1',
            'modifier_group_ids.*' => 'uuid|exists:modifier_groups,id',
        ]);

        // syncWithoutDetaching keeps existing links, only adds new ones
        $product->modifierGroups()->syncWithoutDetaching(
            $request->modifier_group_ids
        );

        return response()->json([
            'data'    => $product->load('modifierGroups.options'),
            'message' => 'Modifier groups linked successfully',
        ]);
    }

    // Route: GET /api/v1/mart/products

    public function products(Request $request)
    {
        // $tenantId = auth()->user()->staff->tenant_id;
        // $branchId = $request->branch_id ?? auth()->user()->staff->branch_id;

        $products = Product::with(['activeUnits'])
            ->where(function ($q) {
                $q->where('product_type', 'retail')
                    ->orWhere('track_stock', true);
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn($p) => [
                'id'             => $p->id,
                'name'           => $p->name,
                'sku'            => $p->sku,
                'image_url'      => $p->image_url,
                'unit'           => $p->unit,
                'stock_quantity' => (float) $p->stock_quantity,
                'reorder_level'  => $p->reorder_level !== null ? (float) $p->reorder_level : null,
                'cost_price'     => (float) $p->cost_price,
                'retail_price'   => (float) $p->retail_price,
                'wholesale_price' => (float) $p->wholesale_price,
                'product_type'   => $p->product_type,
                'active_units'   => $p->activeUnits->map(fn($u) => [
                    'id'           => $u->id,
                    'unit_name'    => $u->unit_name,
                    'unit_label'   => $u->unit_label,
                    'qty_per_base' => (float) $u->qty_per_base,
                    'cost_price'   => (float) $u->cost_price,
                    'retail_price' => (float) $u->retail_price,
                    'wholesale_price' => (float) $u->wholesale_price,
                    'is_base_unit' => (bool) $u->is_base_unit,
                    'barcode'      => $u->barcode,
                ]),
            ]);

        return response()->json(['data' => $products]);
    }
}
