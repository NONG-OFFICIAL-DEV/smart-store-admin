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

}
