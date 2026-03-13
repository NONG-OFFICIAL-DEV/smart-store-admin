<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockAdjustmentController extends Controller
{
    // ── POST /api/v1/mart/stock/adjust ───────────────────────────────────────
    public function adjust(Request $request)
    {
        $request->validate([
            'branch_id'     => 'required|uuid|exists:branches,id',
            'product_id'    => 'required|uuid|exists:products,id',
            'movement_type' => 'required|in:adjustment_in,adjustment_out,waste,count',
            'quantity'      => 'required|numeric|min:0.001',
            'notes'         => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($request) {
            $product   = Product::findOrFail($request->product_id);
            $staff     = auth()->user()->staff;

            // For count: set absolute value. Others: add/subtract.
            $isOut     = in_array($request->movement_type, ['adjustment_out', 'waste']);
            $isCount   = $request->movement_type === 'count';

            $qtyBefore = (float) $product->stock_quantity;

            if ($isCount) {
                $qtyAfter  = (float) $request->quantity;
                $movement  = $qtyAfter - $qtyBefore;
                $product->update(['stock_quantity' => $qtyAfter]);
            } elseif ($isOut) {
                if ($product->stock_quantity < $request->quantity) {
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock. Available: {$product->stock_quantity}",
                    ], 422);
                }
                $movement = -(float) $request->quantity;
                $qtyAfter = $qtyBefore + $movement;
                $product->decrement('stock_quantity', $request->quantity);
            } else {
                $movement = (float) $request->quantity;
                $qtyAfter = $qtyBefore + $movement;
                $product->increment('stock_quantity', $request->quantity);
            }

            $log = StockMovement::create([
                'id'             => Str::uuid(),
                'branch_id'      => $request->branch_id,
                'product_id'     => $product->id,
                'movement_type'  => $request->movement_type,
                'quantity'       => $movement,
                'qty_before'     => $qtyBefore,
                'qty_after'      => $qtyAfter,
                'reference_type' => 'adjustment',
                'notes'          => $request->notes,
                'staff_id'       => $staff?->id,
            ]);

            return response()->json([
                'success'          => true,
                'data'             => [
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'qty_before'   => $qtyBefore,
                    'qty_after'    => $qtyAfter,
                    'movement'     => $movement,
                    'log'          => $log,
                ],
            ]);
        });
    }

    // ── GET /api/v1/mart/stock/movements ─────────────────────────────────────
    public function movements(Request $request)
    {
        $request->validate([
            'branch_id'  => 'required|uuid|exists:branches,id',
            'product_id' => 'nullable|uuid',
            'type'       => 'nullable|string',
            'from'       => 'nullable|date',
            'to'         => 'nullable|date',
            'per_page'   => 'nullable|integer|max:100',
        ]);

        $movements = StockMovement::with([
                'product:id,name,unit,image_url',
                'staff:id,user_id',
                'staff.user:id,first_name,last_name',
            ])
            ->where('branch_id', $request->branch_id)
            ->when($request->product_id, fn($q) => $q->where('product_id', $request->product_id))
            ->when($request->type,       fn($q) => $q->where('movement_type', $request->type))
            ->when($request->from,       fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to,         fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $movements]);
    }

    // ── GET /api/v1/mart/stock/low-stock ─────────────────────────────────────
    public function lowStock(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|uuid|exists:branches,id',
        ]);

        $branch   = \App\Models\Branch::findOrFail($request->branch_id);

        $products = Product::where('tenant_id', $branch->tenant_id)
            ->where('track_stock', true)
            ->where('is_available', true)
            ->whereRaw('stock_quantity <= COALESCE(reorder_level, 0)')
            ->orderBy('stock_quantity')
            ->get(['id', 'name', 'image_url', 'unit', 'stock_quantity', 'reorder_level']);

        return response()->json([
            'success' => true,
            'count'   => $products->count(),
            'data'    => $products,
        ]);
    }
}