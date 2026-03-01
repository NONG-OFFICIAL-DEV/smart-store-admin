<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $query = Staff::query()
            ->with([
                'user',          // ← for full_name, email, phone, avatar
                'role',          // ← for role name
                'branch',        // ← for branch name
            ]);

        // ── Search by user's name or email ────────────────────────────────────────
        if ($search = $request->get('search')) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name',  'like', "%{$search}%")
                    ->orWhere('email',      'like', "%{$search}%")
                    ->orWhere('phone',      'like', "%{$search}%");
            });
        }

        // ── Filter by tenant ──────────────────────────────────────────────────────
        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        // ── Filter by branch ──────────────────────────────────────────────────────
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // ── Filter by role ────────────────────────────────────────────────────────
        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        // ── Filter by status ──────────────────────────────────────────────────────
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // ── Sorting ───────────────────────────────────────────────────────────────
        $allowedSorts = ['created_at', 'hire_date', 'hourly_rate', 'employee_code'];
        $sortBy       = in_array($request->get('sort_by'), $allowedSorts)
            ? $request->get('sort_by')
            : 'created_at';

        $query->orderBy($sortBy, $request->get('sort_order', 'desc') === 'asc' ? 'asc' : 'desc');

        $items = $query->paginate($perPage);

        // ── Transform — add full_name and role_name to each item ──────────────────
        $items->getCollection()->transform(function ($staff) {
            return [
                'id'            => $staff->id,
                'employee_code' => $staff->employee_code,
                'hire_date'     => $staff->hire_date,
                'hourly_rate'   => $staff->hourly_rate,
                'is_active'     => $staff->is_active,
                'created_at'    => $staff->created_at,

                // ── From user relationship ────────────────────────────────────────
                'user_id'       => $staff->user_id,
                'full_name'     => $staff->user?->full_name,   // ← uses $appends accessor
                'email'         => $staff->user?->email,
                'phone'         => $staff->user?->phone,
                'avatar_url'    => $staff->user?->avatar_url,

                // ── From role relationship ────────────────────────────────────────
                'role_id'       => $staff->role_id,
                'role_name'     => $staff->role?->name,        // ← role name

                // ── From branch relationship ──────────────────────────────────────
                'branch_id'     => $staff->branch_id,
                'branch_name'   => $staff->branch?->name,      // ← branch name
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Staff retrieved successfully.',
            'data'    => $items,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        return Staff::store($request);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        return Staff::store($request, $id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $staff = Staff::find($id);

        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff not found',
            ], 404);
        }

        // Soft disable — never hard delete staff
        // They have orders, shifts, payments linked to them
        $staff->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Staff member deactivated successfully',
        ], 200);
    }
}
