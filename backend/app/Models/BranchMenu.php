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

        // ── UPDATE — single record ─────────────────────────────────────────────
        if ($id) {
            $record = static::find($id);
            if (!$record) {
                return response()->json(['error' => 'Record not found'], 404);
            }
            $record->update($data);
            return response()->json([
                'success' => true,
                'data'    => $record->fresh()->load(['branch', 'menu'])
            ], 200);
        }

        // ── CREATE — handle single branch_id OR array of branch_ids ───────────
        $branchIds = is_array($data['branch_id'])
            ? $data['branch_id']        // array → multi assign
            : [$data['branch_id']];     // string → wrap in array

        $menu = Menu::find($data['menu_id']);
        if (!$menu) {
            return response()->json(['error' => 'Menu not found'], 404);
        }

        $created  = [];
        $skipped  = [];
        $errors   = [];

        foreach ($branchIds as $branchId) {

            $branch = Branch::find($branchId);

            // Branch not found
            if (!$branch) {
                $errors[] = "Branch {$branchId} not found";
                continue;
            }

            // Branch and menu must belong to same tenant
            if ($branch->tenant_id !== $menu->tenant_id) {
                $errors[] = "Branch {$branch->name} does not belong to the same business";
                continue;
            }

            // Already assigned — skip, don't error
            $exists = static::where('branch_id', $branchId)
                            ->where('menu_id', $data['menu_id'])
                            ->exists();

            if ($exists) {
                $skipped[] = $branch->name;
                continue;
            }

            // Create the assignment
            $record = static::create([
                'branch_id'      => $branchId,
                'menu_id'        => $data['menu_id'],
                'available_from' => $data['available_from'] ?? null,
                'available_until'=> $data['available_until'] ?? null,
                'days_of_week'   => $data['days_of_week'] ?? null,
                'sort_order'     => $data['sort_order'] ?? 0,
            ]);

            $created[] = $record->load(['branch', 'menu']);
        }

        return response()->json([
            'success' => true,
            'created' => count($created),
            'skipped' => count($skipped),
            'data'    => $created,
            // Only show messages if something was skipped or errored
            'messages' => array_filter([
                count($skipped) ? count($skipped) . ' already assigned: ' . implode(', ', $skipped) : null,
                count($errors)  ? implode(', ', $errors) : null,
            ]),
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
