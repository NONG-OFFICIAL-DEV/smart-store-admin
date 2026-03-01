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
        //
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
}
