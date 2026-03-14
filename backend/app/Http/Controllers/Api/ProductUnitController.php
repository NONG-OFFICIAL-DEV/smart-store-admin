<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductUnitController extends Controller
{
    // GET /api/v1/products/{product}/units
    public function index(Product $product)
    {
        return response()->json([
            'success' => true,
            'data'    => $product->units,
        ]);
    }

    // POST /api/v1/products/{product}/units
    public function store(Request $request, Product $product)
    {
        $request->validate([
            'unit_name'       => 'required|string|max:50',
            'unit_label'      => 'nullable|string|max:50',
            'qty_per_base'    => 'required|numeric|min:0.001',
            'barcode'         => 'nullable|string|max:60|unique:product_units,barcode',
            'retail_price'    => 'required|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'cost_price'      => 'nullable|numeric|min:0',
            'is_base_unit'    => 'boolean',
            'is_active'       => 'boolean',
            'sort_order'      => 'integer',
        ]);

        // Only one base unit per product
        if ($request->boolean('is_base_unit')) {
            $product->units()->update(['is_base_unit' => false]);
        }

        $unit = ProductUnit::create([
            'id'              => (string) Str::uuid(),
            'product_id'      => $product->id,
            'unit_name'       => $request->unit_name,
            'unit_label'      => $request->unit_label,
            'qty_per_base'    => $request->qty_per_base,
            'barcode'         => $request->barcode,
            'retail_price'    => $request->retail_price,
            'wholesale_price' => $request->wholesale_price,
            'cost_price'      => $request->cost_price,
            'is_base_unit'    => $request->boolean('is_base_unit', false),
            'is_active'       => $request->boolean('is_active', true),
            'sort_order'      => $request->sort_order ?? 0,
        ]);

        return response()->json(['success' => true, 'data' => $unit], 201);
    }

    // PUT /api/v1/products/{product}/units/{unit}
    public function update(Request $request, Product $product, ProductUnit $unit)
    {
        $request->validate([
            'unit_name'       => 'sometimes|string|max:50',
            'unit_label'      => 'nullable|string|max:50',
            'qty_per_base'    => 'sometimes|numeric|min:0.001',
            'barcode'         => 'nullable|string|max:60|unique:product_units,barcode,' . $unit->id,
            'retail_price'    => 'sometimes|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'cost_price'      => 'nullable|numeric|min:0',
            'is_base_unit'    => 'boolean',
            'is_active'       => 'boolean',
            'sort_order'      => 'integer',
        ]);

        if ($request->boolean('is_base_unit') && !$unit->is_base_unit) {
            $product->units()->where('id', '!=', $unit->id)->update(['is_base_unit' => false]);
        }

        $unit->update($request->only([
            'unit_name',
            'unit_label',
            'qty_per_base',
            'barcode',
            'retail_price',
            'wholesale_price',
            'cost_price',
            'is_base_unit',
            'is_active',
            'sort_order',
        ]));

        return response()->json(['success' => true, 'data' => $unit->fresh()]);
    }

    // DELETE /api/v1/products/{product}/units/{unit}
    public function destroy(Product $product, ProductUnit $unit)
    {
        if ($unit->is_base_unit && $product->units()->count() > 1) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete base unit while other units exist. Set another unit as base first.',
            ], 422);
        }

        $unit->delete();

        return response()->json(['success' => true, 'message' => 'Unit deleted']);
    }

    public function names(Request $request)
    {
        $names = ProductUnit::whereHas('product')
            ->select('unit_name', 'unit_label', 'qty_per_base')
            ->distinct('unit_name')
            ->orderBy('unit_name')
            ->get()
            ->map(fn($u) => [
                'title'        => $u->unit_name,
                'unit_label'   => $u->unit_label,
                'qty_per_base' => (float) $u->qty_per_base,
            ]);

        return response()->json(['data' => $names]);
    }
}
