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
        // $tenantId = auth()->user()->staff->tenant_id;

        $products = Product::with(['activeUnits'])
            // ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('product_type', 'retail')
                    ->orWhere('track_stock', true);
            })
            // ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn($p) => [
                'id'              => $p->id,
                'name'            => $p->name,
                'sku'             => $p->sku,
                'image_url'       => $p->image_url,
                'unit'            => $p->unit,
                'stock_quantity'  => (float) $p->stock_quantity,
                'reorder_level'   => $p->reorder_level !== null ? (float) $p->reorder_level : null,
                'cost_price'      => (float) $p->cost_price,
                'retail_price'    => (float) $p->retail_price,
                'wholesale_price' => (float) $p->wholesale_price,
                'product_type'    => $p->product_type,
                'active_units'    => $p->activeUnits->map(fn($u) => [
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
            ]);

        return response()->json(['data' => $products]);
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
                'product_type'    => $product->product_type,
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
