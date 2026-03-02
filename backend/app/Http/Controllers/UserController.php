<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // LIST ALL USERS
    public function index(Request $request)
    {
        $query = User::query();

        // ── Keyword search (email, first_name, last_name, phone) ──────────────
        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('email',      'like', "%{$keyword}%")
                    ->orWhere('first_name', 'like', "%{$keyword}%")
                    ->orWhere('last_name', 'like', "%{$keyword}%")
                    ->orWhere('phone',     'like', "%{$keyword}%");
            });
        }

        // ── Status filter (is_active: true/false) ─────────────────────────────
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // ── Email verified filter ─────────────────────────────────────────────
        if ($request->filled('verified')) {
            if (filter_var($request->verified, FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
        }

        // ── Eager load staff profiles with role (role lives on Staff not User) ─
        $query->with([
            'staff.role',
            'staff.branch',
        ]);

        // ── Sorting ───────────────────────────────────────────────────────────
        $sortBy  = $request->get('sort_by',  'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $allowedSorts = ['created_at', 'first_name', 'last_name', 'email'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        // ── Pagination ────────────────────────────────────────────────────────
        $perPage = min((int) $request->get('per_page', 15), 100);
        $users   = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $users
        ]);
    }

    // CREATE USER
    public function store(Request $request)
    {
        return User::store($request);
    }

    // SHOW SINGLE USER
    public function show($id)
    {
        $user = User::with('role')->findOrFail($id);
        return response()->json($user, 200);
    }

    // UPDATE USER
    public function update(Request $request, $id)
    {
        // $user = User::findOrFail($id);

        // $data = [
        //     'name' => $request->name,
        //     'email'     => $request->email,
        //     'username'  => $request->username,
        //     'role_id'   => $request->role_id,
        //     'status'    => $request->status,
        // ];

        // if ($request->password) {
        //     $data['password'] = Hash::make($request->password);
        // }

        // $user->update($data);

        // return response()->json([
        //     'message' => 'User updated successfully',
        //     'data' => $user->load('role')
        // ], 200);

        return User::store($request, $id);
    }

    // DELETE USER
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Protect admin users
        if ($user->role && $user->role->slug === 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Admin user cannot be deleted'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'User deleted successfully'
        ]);
    }
}
