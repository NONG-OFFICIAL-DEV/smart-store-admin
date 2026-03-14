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
                'bu_type'        => $ownedTenant->bu_type,
                'logo_url'       => $ownedTenant->logo_url,
                'branch_id'      => null,       // access ALL branches
                'permissions'    => Permission::pluck('code')->toArray(),
            ]);
        }
        // ── 4. Regular Staff ───────────────────────────────────────────────
        $staff = $user->staff()
            ->withoutGlobalScopes()          // ← bypass TenantScope
            ->with(['role.permissions', 'branch', 'tenant'])
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
            'bu_type'        => $staff->tenant?->bu_type,
            'logo_url'       => $staff->tenant?->logo_url,
            'branch_name' => $staff->branch?->name,
            'branch_id'      => $staff->branch_id,
            'role_name'    => $staff->role->name,
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
}
