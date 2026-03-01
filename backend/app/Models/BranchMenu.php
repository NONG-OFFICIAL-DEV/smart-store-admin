<?php

namespace App\Models;

use Illuminate\Http\Request;

class BranchMenu extends BaseModel
{
    protected $table = 'branch_menus';

    protected $fillable = [
        'branch_id',
        'menu_id',
        'available_from',
        'available_until',
        'days_of_week',
        'sort_order',
    ];

    protected $casts = [
        'days_of_week' => 'array',   // stored as JSON, returned as PHP array
        'sort_order'   => 'integer',
    ];

    // ─── Store (create or update) ─────────────────────────────────────────────
    public static function store(array|Request $request, ?string $id = null)
    {
        $data = $request instanceof Request
            ? $request->only([
                'branch_id',
                'menu_id',
                'available_from',
                'available_until',
                'days_of_week',
                'sort_order',
            ])
            : $request;

        // ── Safety check: branch and menu must belong to same tenant ──────────
        if ($request instanceof Request) {
            $branch = Branch::find($data['branch_id']);
            $menu   = Menu::find($data['menu_id']);

            if (!$branch || !$menu) {
                return response()->json([
                    'error' => 'Branch or Menu not found'
                ], 404);
            }

            if ($branch->tenant_id !== $menu->tenant_id) {
                return response()->json([
                    'error' => 'Branch and Menu must belong to the same business'
                ], 403);
            }
        }

        // ── If updating, just find and update ─────────────────────────────────
        if ($id) {
            $record = static::find($id);
            if (!$record) {
                return response()->json(['error' => 'Record not found'], 404);
            }
            $record->update($data);
            return response()->json(['success' => true, 'data' => $record->fresh()->load(['branch', 'menu'])], 200);
        }

        // ── If creating, check not already assigned ───────────────────────────
        $exists = static::where('branch_id', $data['branch_id'])
                        ->where('menu_id', $data['menu_id'])
                        ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'This menu is already assigned to this branch'
            ], 422);
        }

        $record = static::create($data);
        return response()->json([
            'success' => true,
            'data'    => $record->load(['branch', 'menu'])
        ], 201);
    }

    // ─── Remove assignment ────────────────────────────────────────────────────
    public static function unassign(string $branchId, string $menuId)
    {
        $record = static::where('branch_id', $branchId)
                        ->where('menu_id', $menuId)
                        ->first();

        if (!$record) {
            return response()->json(['error' => 'Assignment not found'], 404);
        }

        $record->delete();
        return response()->json(['success' => true, 'message' => 'Menu removed from branch'], 200);
    }

    // ─── Relationships ────────────────────────────────────────────────────────
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    // Check if this menu is available right now
    public function isAvailableNow(): bool
    {
        $now     = now();
        $today   = $now->dayOfWeek; // 0 = Sunday, 6 = Saturday
        $nowTime = $now->format('H:i:s');

        // Check day of week
        if ($this->days_of_week && !in_array($today, $this->days_of_week)) {
            return false;
        }

        // Check time window
        if ($this->available_from && $nowTime < $this->available_from) {
            return false;
        }
        if ($this->available_until && $nowTime > $this->available_until) {
            return false;
        }

        return true;
    }
}
