<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BranchMenu;
use Illuminate\Http\Request;

class BranchMenuController extends Controller
{
    /**
     * GET /api/v1/branch-menus
     * Get all menu assignments (optionally filter by branch or menu)
     *
     * Query params:
     *   ?branch_id=uuid   → all menus assigned to a branch
     *   ?menu_id=uuid     → all branches that have this menu
     */
    public function index(Request $request)
    {
        $query = BranchMenu::with(['branch', 'menu']);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('menu_id')) {
            $query->where('menu_id', $request->menu_id);
        }

        $data = $query->orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'data'    => $data
        ]);
    }

    /**
     * POST /api/v1/branch-menus
     * Assign a menu to a branch
     *
     * Body:
     * {
     *   "branch_id":       "uuid",
     *   "menu_id":         "uuid",
     *   "available_from":  "08:00",   (optional)
     *   "available_until": "22:00",   (optional)
     *   "days_of_week":    [0,1,2,3,4,5,6], (optional, 0=Sun 6=Sat)
     *   "sort_order":      1          (optional)
     * }
     */
    public function store(Request $request)
    {
        $request->validate([
            'branch_id'       => 'required|uuid|exists:branches,id',
            'menu_id'         => 'required|uuid|exists:menus,id',
            'available_from'  => 'nullable|date_format:H:i',
            'available_until' => 'nullable|date_format:H:i|after:available_from',
            'days_of_week'    => 'nullable|array',
            'days_of_week.*'  => 'integer|min:0|max:6',
            'sort_order'      => 'nullable|integer|min:0',
        ]);

        return BranchMenu::store($request);
    }

    /**
     * GET /api/v1/branch-menus/{id}
     * Get one assignment
     */
    public function show(string $id)
    {
        $record = BranchMenu::with(['branch', 'menu.categories.products'])->find($id);

        if (!$record) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $record
        ]);
    }

    /**
     * PUT /api/v1/branch-menus/{id}
     * Update assignment (change time slots or days)
     *
     * Body:
     * {
     *   "available_from":  "09:00",
     *   "available_until": "21:00",
     *   "days_of_week":    [1,2,3,4,5],
     *   "sort_order":      2
     * }
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'available_from'  => 'nullable|date_format:H:i',
            'available_until' => 'nullable|date_format:H:i|after:available_from',
            'days_of_week'    => 'nullable|array',
            'days_of_week.*'  => 'integer|min:0|max:6',
            'sort_order'      => 'nullable|integer|min:0',
        ]);

        return BranchMenu::store($request, $id);
    }

    /**
     * DELETE /api/v1/branch-menus/{id}
     * Remove a menu from a branch
     */
    public function destroy(string $id)
    {
        $record = BranchMenu::find($id);

        if (!$record) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Menu removed from branch'
        ]);
    }

    /**
     * GET /api/v1/branch-menus/branch/{branchId}/available-now
     * Get only menus that are available RIGHT NOW for a branch
     */
    public function availableNow(string $branchId)
    {
        $assignments = BranchMenu::with(['menu.categories.products'])
            ->where('branch_id', $branchId)
            ->get();

        // Filter only currently available menus
        $available = $assignments->filter(fn($a) => $a->isAvailableNow())->values();

        return response()->json([
            'success' => true,
            'time'    => now()->format('H:i'),
            'day'     => now()->dayOfWeek,
            'data'    => $available
        ]);
    }

    /**
     * DELETE /api/v1/branch-menus/unassign
     * Unassign by branch_id + menu_id directly
     *
     * Body:
     * {
     *   "branch_id": "uuid",
     *   "menu_id":   "uuid"
     * }
     */
    public function unassign(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|uuid',
            'menu_id'   => 'required|uuid',
        ]);

        return BranchMenu::unassign($request->branch_id, $request->menu_id);
    }
}
