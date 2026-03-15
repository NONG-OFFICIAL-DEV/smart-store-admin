<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Order;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $query = Branch::with('tenant');  // 👈 eager load

        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $query->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_order', 'desc'));

        $items = $query->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Branches retrieved successfully.',
            'data'    => $items,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        return Branch::store($request);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $branch = Branch::with([
            'tenant',
            'menus',                    // assigned menus via branch_menus
            'staff.user',               // staff with user info
            'staff.role',               // staff roles
            'tables',                   // tables in this branch
        ])
            ->findOrFail($id);

        // ── Today's stats ──────────────────────────────────────────────────
        $today = now()->startOfDay();

        $todayOrders = Order::where('branch_id', $id)
            ->whereDate('created_at', $today)
            ->get();

        $stats = [
            'orders_today'  => $todayOrders->count(),
            'revenue_today' => $todayOrders->sum('total_amount'),
            'avg_order'     => $todayOrders->count()
                ? round($todayOrders->avg('total_amount'), 2)
                : 0,
        ];

        // ── Table status summary ───────────────────────────────────────────
        $tableSummary = [
            'total'     => $branch->tables->count(),
            'available' => $branch->tables->where('status', 'available')->count(),
            'occupied'  => $branch->tables->where('status', 'occupied')->count(),
            'reserved'  => $branch->tables->where('status', 'reserved')->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => [
                'branch'        => $branch,
                'stats'         => $stats,
                'table_summary' => $tableSummary,
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        return Branch::store($request, $id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $record = Branch::find($id);

        if (!$record) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Branch removed'
        ]);
    }

    public function toggleOpen(string $id)
    {
        $branch          = Branch::findOrFail($id);
        $branch->is_open = !$branch->is_open;
        $branch->save();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'      => $branch->id,
                'is_open' => $branch->is_open,
            ]
        ]);
    }
}
