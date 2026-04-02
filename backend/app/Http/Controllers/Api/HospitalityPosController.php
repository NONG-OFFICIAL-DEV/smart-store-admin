<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use Illuminate\Http\Request;

class HospitalityPosController extends Controller
{
       // ── GET /api/v1/hospitality/pos/products ─────────────────────────────────────────
    // Products with their units — for POS product grid in hospitality module (restaurant, cafe, etc.)
    public function productHospitalityPos(Request $request)
    {
        // $tenantId = auth()->user()->staff->tenant_id;
        // $branch = auth()->user()->staff->branch_id;

        $request->validate([
            'branch_id'   => 'required|uuid|exists:branches,id',
            'category_id' => 'nullable|uuid',
            'search'      => 'nullable|string',
        ]);

        $branch = Branch::findOrFail($request->branch_id);

        $products = Product::with(['variants', 'category:id,name'])

            ->where('tenant_id', $branch->tenant_id)
            ->where('is_available', true)
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when(
                $request->search,
                fn($q) =>
                $q->where('name', 'ilike', "%{$request->search}%")
                    ->orWhere('barcode', $request->search)
                    ->orWhereHas(
                        'activeUnits',
                        fn($u) =>
                        $u->where('barcode', $request->search)
                    )
            )
            ->orderBy('sort_order')
            ->paginate(40);

        return response()->json(['success' => true, 'data' => $products]);
    }

    // public function categories(Request $request)
    // {
    //     $request->validate([
    //         'branch_id' => 'required|uuid|exists:branches,id',
    //     ]);

    //     $branch = Branch::findOrFail($request->branch_id);

    //     $categories = Category::whereHas('products', function ($q) use ($branch) {
    //         $q->where('tenant_id', $branch->tenant_id)
    //             // ->where(function ($q) {
    //             //     $q->where('product_type', 'retail')
    //             //         ->orWhere('track_stock', true);
    //             // })
    //             ->where('is_available', true);
    //     })
    //         ->where('is_active', true)
    //         ->orderBy('sort_order')
    //         ->get(['id', 'name', 'icon', 'color', 'image_url', 'sort_order']);

    //     return response()->json(['success' => true, 'data' => $categories]);
    // }
}
