<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffShift;
use App\Services\TenantResolver;
use Illuminate\Http\Request;

class ShiftAssignmentController extends Controller
{
    public function __construct(
        private TenantResolver $tenantResolver
    ) {}

    private function tenantId(): ?string
    {
        $user = auth()->user();
        return $user->ownedTenant?->id
            ?? $user->staff()->withoutGlobalScopes()->first()?->tenant_id;
    }

    private function isSuperAdmin(): bool
    {
        $user = auth()->user();
        // ← swap this check to match your actual super admin flag
        return $user->is_super_admin ?? false;
    }

    public function index(Request $request)
    {
        $query = StaffShift::query()
            ->with([
                'shift',
                'staff.user',
                'staff.role',
                'branch',
            ]);

        // ── Tenant scoping ────────────────────────────────────────
        if (! $this->isSuperAdmin()) {
            $tenantId = $this->tenantId();

            // StaffShift has no tenant_id, so scope through staff relation
            $query->whereHas('staff', function ($q) use ($tenantId) {
                $q->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId);
            });
        }
        // super admin → no tenant filter, gets everything

        // ── Filters ───────────────────────────────────────────────
        if ($request->filled('shift_id')) {
            $query->where('shift_id', $request->shift_id);
        }

        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('shift_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('shift_date', '<=', $request->date_to);
        }

        if ($request->boolean('today')) {
            $query->whereDate('shift_date', today());
        }

        $assignments = $query
            ->orderBy('shift_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(min((int) $request->get('per_page', 20), 100));

        return response()->json([
            'status' => 'success',
            'data'   => $assignments->items(),
            'pagination' => [
                'total'        => $assignments->total(),
                'per_page'     => $assignments->perPage(),
                'current_page' => $assignments->currentPage(),
                'last_page'    => $assignments->lastPage(),
            ],
        ]);
    }

    // ── POST /api/v1/shift-assignments ────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'shift_id'  => 'required|uuid|exists:shifts,id',
            'staff_id'  => 'required|uuid|exists:staff,id',
            'branch_id' => 'required|uuid|exists:branches,id',
            'shift_date' => 'required|date|date_format:Y-m-d',
            'notes'     => 'nullable|string',
        ]);

        // Check for duplicate assignment
        $exists = StaffShift::where('shift_id',   $request->shift_id)
            ->where('staff_id',   $request->staff_id)
            ->where('branch_id',  $request->branch_id)
            ->where('shift_date', $request->shift_date)
            ->exists();

        if ($exists) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This staff member is already assigned to this shift on this date',
            ], 422);
        }

        $assignment = StaffShift::create($request->only([
            'shift_id',
            'staff_id',
            'branch_id',
            'shift_date',
            'notes',
        ]));

        return response()->json([
            'status'  => 'success',
            'message' => 'Staff assigned to shift successfully',
            'data'    => $assignment->load(['shift', 'staff.user', 'branch']),
        ], 201);
    }

    // ── GET /api/v1/shift-assignments/{id} ────────────────────────────────────
    public function show(string $id)
    {
        $assignment = StaffShift::with(['shift', 'staff.user', 'branch'])->find($id);

        if (!$assignment) {
            return response()->json(['status' => 'error', 'message' => 'Assignment not found'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $assignment,
        ]);
    }

    // ── PUT /api/v1/shift-assignments/{id} ────────────────────────────────────
    public function update(Request $request, string $id)
    {
        $assignment = StaffShift::find($id);

        if (!$assignment) {
            return response()->json(['status' => 'error', 'message' => 'Assignment not found'], 404);
        }

        $request->validate([
            'branch_id'    => 'sometimes|uuid|exists:branches,id',
            'shift_date'   => 'sometimes|date|date_format:Y-m-d',
            'actual_start' => 'nullable|date',
            'actual_end'   => 'nullable|date|after:actual_start',
            'notes'        => 'nullable|string',
        ]);

        $assignment->update($request->only([
            'branch_id',
            'shift_date',
            'actual_start',
            'actual_end',
            'notes',
        ]));

        return response()->json([
            'status'  => 'success',
            'message' => 'Assignment updated successfully',
            'data'    => $assignment->fresh()->load(['shift', 'staff.user', 'branch']),
        ]);
    }

    // ── DELETE /api/v1/shift-assignments/{id} ─────────────────────────────────
    public function destroy(string $id)
    {
        $assignment = StaffShift::find($id);

        if (!$assignment) {
            return response()->json(['status' => 'error', 'message' => 'Assignment not found'], 404);
        }

        $assignment->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Assignment removed successfully',
        ]);
    }

    // ── POST /api/v1/shift-assignments/{id}/clock-in ──────────────────────────
    public function clockIn(string $id)
    {
        $assignment = StaffShift::find($id);

        if (!$assignment) {
            return response()->json(['status' => 'error', 'message' => 'Assignment not found'], 404);
        }

        if ($assignment->actual_start) {
            return response()->json(['status' => 'error', 'message' => 'Already clocked in'], 422);
        }

        $assignment->update(['actual_start' => now()]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Clocked in successfully',
            'data'    => $assignment->fresh()->load(['shift', 'staff.user', 'branch']),
        ]);
    }

    // ── POST /api/v1/shift-assignments/{id}/clock-out ─────────────────────────
    public function clockOut(string $id)
    {
        $assignment = StaffShift::find($id);

        if (!$assignment) {
            return response()->json(['status' => 'error', 'message' => 'Assignment not found'], 404);
        }

        if (!$assignment->actual_start) {
            return response()->json(['status' => 'error', 'message' => 'Must clock in first'], 422);
        }

        if ($assignment->actual_end) {
            return response()->json(['status' => 'error', 'message' => 'Already clocked out'], 422);
        }

        $assignment->update(['actual_end' => now()]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Clocked out successfully',
            'data'    => $assignment->fresh()->load(['shift', 'staff.user', 'branch']),
        ]);
    }
}
