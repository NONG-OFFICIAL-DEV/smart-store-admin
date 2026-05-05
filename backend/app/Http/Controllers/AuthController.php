<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Tenant;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    // Login user
    public function login(Request $request)
    {
        // ── Validate input ────────────────────────────────────────────────────
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // ── Check user exists first (to give specific error messages) ─────────
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status'  => 'invalid_credentials',
                'message' => 'Email or password is incorrect',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'status'  => 'account_inactive',
                'message' => 'Your account has been deactivated. Please contact support.',
            ], 403);
        }

        // ── Attempt JWT login ─────────────────────────────────────────────────
        // NOTE: JWTAuth::attempt() calls getAuthPassword() which returns password_hash
        // so it correctly checks against our password_hash column
        try {
            $credentials = [
                'email'    => $request->email,
                'password' => $request->password,
            ];

            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json([
                    'status'  => 'invalid_credentials',
                    'message' => 'Email or password is incorrect',
                ], 401);
            }

            // ── Update last_login_at ──────────────────────────────────────────
            $user->update([
                'last_login_at' => now(),
            ]);
            $user = $user->fresh(); // ← reload from DB so last_login_at is no longer null

            // ── Log login ─────────────────────────────────────────────────────
            ActivityLog::log(
                action: 'auth.login',
                entity: null,
                payload: null,
                description: "User {$user->email} logged in"
            );

            return response()->json([
                'status'     => 'success',
                'token'      => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user'       => [
                    'id'            => $user->id,
                    'email'         => $user->email,
                    'phone'         => $user->phone,
                    'first_name'    => $user->first_name,
                    'last_name'     => $user->last_name,
                    'full_name'     => $user->full_name,
                    'avatar_url'    => $user->avatar_url,
                    'is_active'     => $user->is_active,
                    'last_login_at' => $user->last_login_at,  // ← now updated
                ],
                // ── Role flags at top level too so authStore can read immediately ──
                'is_super_admin' => $user->is_super_admin,
                'is_owner'       => (bool) Tenant::where('owner_user_id', $user->id)->exists(),
                // ── ADD THESE ──────────────────────────────────────────────
                'bu_type' => $user->is_super_admin ? null
                    : (Tenant::where('owner_user_id', $user->id)->value('bu_type')
                        ?? $user->staff()->withoutGlobalScopes()->with('tenant')->first()?->tenant?->bu_type),

            ]);
        } catch (JWTException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Could not create token, please try again',
            ], 500);
        } catch (\Exception $e) {
            // \Log::error('Login Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }


    public function me(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        // ── 1. Super Admin ─────────────────────────────────────────
        // No tenant, no branch, no role — bypasses everything
        if ($user->is_super_admin) {
            return response()->json([
                'user'           => $user,
                'is_super_admin' => true,
                'is_owner'       => false,
                'tenant_id'      => null,
                'branch_id'      => null,
                'permissions'    => Permission::pluck('code')->toArray(),
            ]);
        }

        // ── 2. Tenant Owner ────────────────────────────────────────
        // Owns a tenant — full access within that tenant
        $ownedTenant = Tenant::where('owner_user_id', $user->id)->first();
        if ($ownedTenant) {
            return response()->json([
                'user'           => $user,
                'is_super_admin' => false,
                'is_owner'       => true,
                'tenant_id'      => $ownedTenant->id,
                'bu_name'        => $ownedTenant->name,
                'bu_type'        => $ownedTenant->businessType?->code,   // ← was ->bu_type
                'logo_url'       => $ownedTenant->logo_url,
                'currency'       => $ownedTenant->currency,
                'locale'         => $ownedTenant->locale,
                'plan'           => $ownedTenant->plan,
                'branch_id'      => null,       // access ALL branches
                'permissions'    => Permission::pluck('code')->toArray(),
            ]);
        }
        // ── 4. Regular Staff ───────────────────────────────────────────────
        $staff = $user->staff()
            ->withoutGlobalScopes()          // ← bypass TenantScope
            ->with(['role.permissions', 'branch', 'tenant.businessType'])
            ->first();

        if (!$staff) {
            return response()->json(['error' => 'No active staff record'], 403);
        }

        return response()->json([
            'user'           => $user,
            'is_super_admin' => false,
            'is_owner'       => false,
            'is_staff'       => true,
            'tenant_id'      => $staff->tenant_id,
            'bu_name'        => $staff->tenant?->name,
            'bu_type'        => $staff->tenant?->businessType?->code,   // ← was ->bu_type
            'logo_url'       => $staff->tenant?->logo_url,
            'branch_name'    => $staff->branch?->name,
            'branch_id'      => $staff->branch_id,
            'role_name'      => $staff->role->name,
            'currency'       => $staff->tenant?->currency,
            'plan'           => $staff->tenant?->currency,
            'permissions'    => $staff->role->permissions->pluck('code')->toArray(),
        ]);
    }

    // Logout
    public function logout()
    {
        try {
            // ── Log before invalidating token ──────────────────────────────
            ActivityLog::log(
                action: 'auth.logout',
                entity: null,
                payload: null,
                description: 'User logged out'
            );
            JWTAuth::parseToken()->invalidate();
            return response()->json(['message' => 'Logged out successfully']);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Failed to logout, token invalid'], 500);
        }
    }

    // Login by PIN (POS / quick login)
    // POST /api/auth/login-pin
    // Body: { "pin_code": "1122", "branch_id": "<uuid>" }   (branch_id optional — narrows search)
    public function loginByPin(Request $request)
    {
        // ── Validate ──────────────────────────────────────────────────────────
        $request->validate([
            'pin_code'  => 'required|string|min:4|max:6',
            'branch_id' => 'nullable|uuid|exists:branches,id',
        ]);

        // ── Find active staff in this branch that match the PIN ───────────────
        // We load a small set (active staff of the branch) then verify hash in PHP
        // because bcrypt hashes cannot be searched directly in SQL.
        $staffQuery = \App\Models\Staff::withoutGlobalScopes()
            ->with(['user', 'role.permissions', 'tenant', 'branch'])
            ->where('is_active', true)
            ->whereNotNull('pin_code');

        if ($request->filled('branch_id')) {
            $staffQuery->where('branch_id', $request->branch_id);
        }

        $matchedStaff = null;

        // Iterate and verify hashed pin — stop at first match
        foreach ($staffQuery->cursor() as $staff) {
            if (Hash::check($request->pin_code, $staff->pin_code)) {
                $matchedStaff = $staff;
                break;
            }
        }

        if (!$matchedStaff) {
            return response()->json([
                'status'  => 'invalid_pin',
                'message' => 'PIN code is incorrect or not assigned to any active staff.',
            ], 401);
        }

        $user = $matchedStaff->user;

        if (!$user || !$user->is_active) {
            return response()->json([
                'status'  => 'account_inactive',
                'message' => 'This account has been deactivated. Please contact support.',
            ], 403);
        }

        // ── Generate JWT for this user ────────────────────────────────────────
        try {
            $token = JWTAuth::fromUser($user);
        } catch (JWTException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Could not create token, please try again.',
            ], 500);
        }

        // ── Update last_login_at ──────────────────────────────────────────────
        $user->update(['last_login_at' => now()]);
        $user = $user->fresh();

        // ── Activity log ──────────────────────────────────────────────────────
        ActivityLog::log(
            action: 'auth.login_pin',
            entity: null,
            payload: null,
            description: "Staff {$user->email} logged in via PIN (branch: {$matchedStaff->branch_id})"
        );

        return response()->json([
            'status'     => 'success',
            'token'      => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'user' => [
                'id'            => $user->id,
                'email'         => $user->email,
                'phone'         => $user->phone,
                'first_name'    => $user->first_name,
                'last_name'     => $user->last_name,
                'full_name'     => $user->full_name,
                'avatar_url'    => $user->avatar_url,
                'is_active'     => $user->is_active,
                'last_login_at' => $user->last_login_at,
            ],
            'is_super_admin' => false,
            'is_owner'       => false,
            'is_staff'       => true,
            'tenant_id'      => $matchedStaff->tenant_id,
            'bu_name'        => $matchedStaff->tenant?->name,
            'bu_type'        => $matchedStaff->tenant?->bu_type,
            'logo_url'       => $matchedStaff->tenant?->logo_url,
            'branch_id'      => $matchedStaff->branch_id,
            'branch_name'    => $matchedStaff->branch?->name,
            'role_name'      => $matchedStaff->role?->name,
            'currency'       => $matchedStaff->tenant?->currency,
            'permissions'    => $matchedStaff->role?->permissions->pluck('code')->toArray() ?? [],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // SET PIN — lets a staff member set/change their own PIN after normal login
    // PUT /api/auth/set-pin
    // Body: { "pin_code": "1122" }
    // ─────────────────────────────────────────────────────────────────────────────
    public function setPin(Request $request)
    {
        $request->validate([
            'pin_code' => 'required|string|min:4|max:6|regex:/^\d+$/',
        ]);

        $user  = JWTAuth::parseToken()->authenticate();
        $staff = $user->staff()->withoutGlobalScopes()->first();

        if (!$staff) {
            return response()->json(['status' => 'error', 'message' => 'No staff record found.'], 404);
        }

        $staff->update(['pin_code' => Hash::make($request->pin_code)]);

        return response()->json(['status' => 'success', 'message' => 'PIN updated successfully.']);
    }
}
