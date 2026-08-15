<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\ActivityLog;
use App\Rules\PasswordPolicy;
use App\Services\PasswordService;
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

        // ── Check user exists first ───────────────────────────────────────────
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
            $user->update(['last_login_at' => now()]);
            $user = $user->fresh();

            // ── Resolve bu_type via relation (bu_type column was dropped) ─────
            $bu_type = null;

            if (!$user->is_super_admin) {
                $ownedTenant = Tenant::with('businessType')
                    ->where('owner_user_id', $user->id)
                    ->first();

                if ($ownedTenant) {
                    $bu_type = $ownedTenant->businessType?->code;
                } else {
                    $bu_type = $user->staff()
                        ->withoutGlobalScopes()
                        ->with('tenant.businessType')
                        ->first()
                        ?->tenant
                        ?->businessType
                        ?->code;
                }
            }

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
                    'last_login_at' => $user->last_login_at,
                ],
                'is_super_admin'       => $user->is_super_admin,
                'is_owner'             => Tenant::where('owner_user_id', $user->id)->exists(),
                'bu_type'              => $bu_type,
                'must_change_password' => $user->must_change_password,
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Could not create token, please try again',
            ], 500);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }


    public function me(Request $request)
    {
        // ── jwt.auth middleware already authenticated this request —
        // reuse its resolved user instead of re-parsing the token ────────────
        $user = auth()->user();

        // Common shape returned for every branch below; each branch only
        // overrides what actually differs, so the frontend never has to
        // guess whether a key is simply missing vs. genuinely null.
        $base = [
            'user'              => $user,
            'is_super_admin'    => false,
            'is_owner'          => false,
            'is_staff'          => false,
            'tenant_id'         => null,
            'bu_name'           => null,
            'business_type_id'  => null,
            'bu_type'           => null,
            'logo_url'          => null,
            'currency'          => null,
            'locale'            => null,
            'plan'              => null,
            'branch_id'         => null,
            'branch_name'       => null,
            'role_name'         => null,
            'permissions'       => [],
            'must_change_password' => $user->must_change_password,
        ];

        // ── 1. Super Admin ─────────────────────────────────────────
        // No tenant, no branch, no role — bypasses everything
        if ($user->is_super_admin) {
            return response()->json(array_merge($base, [
                'is_super_admin' => true,
                'permissions'    => Permission::allCodesCached(),
            ]));
        }

        // ── 2. Tenant Owner ────────────────────────────────────────
        // Owns a tenant — full access within that tenant
        $ownedTenant = Tenant::where('owner_user_id', $user->id)
            ->with(['businessType', 'activeSubscription.plan'])
            ->first();
        if ($ownedTenant) {
            $ownerRole = Role::withoutGlobalScopes()
                ->where('tenant_id', $ownedTenant->id)
                ->where('code', Role::OWNER_CODE)
                ->with('permissions:id,code')
                ->first();

            return response()->json(array_merge($base, [
                'is_owner'         => true,
                'tenant_id'        => $ownedTenant->id,
                'bu_name'          => $ownedTenant->name,
                'business_type_id' => $ownedTenant->business_type_id,
                'bu_type'          => $ownedTenant->businessType?->code,
                'logo_url'         => $ownedTenant->logo_url,
                'currency'         => $ownedTenant->currency,
                'locale'           => $ownedTenant->locale,
                'plan'             => $ownedTenant->activeSubscription?->plan?->code,
                'branch_id'        => null,       // access ALL branches
                'role_name'        => 'Owner',
                // Falls back to the full catalog if the Owner role somehow
                // hasn't been provisioned yet for this tenant.
                'permissions'      => $ownerRole?->permissions->pluck('code')->toArray()
                    ?? Permission::allCodesCached(),
            ]));
        }

        // ── 3. Regular Staff ───────────────────────────────────────────────
        $staff = $user->staff()
            ->withoutGlobalScopes()          // ← bypass TenantScope
            ->with(['role.permissions', 'branch', 'tenant.businessType', 'tenant.activeSubscription.plan'])
            ->first();

        if (!$staff) {
            return response()->json(['error' => 'No active staff record'], 403);
        }

        return response()->json(array_merge($base, [
            'is_staff'         => true,
            'tenant_id'        => $staff->tenant_id,
            'bu_name'          => $staff->tenant?->name,
            'business_type_id' => $staff->tenant?->business_type_id,
            'bu_type'          => $staff->tenant?->businessType?->code,
            'logo_url'         => $staff->tenant?->logo_url,
            'branch_name'      => $staff->branch?->name,
            'branch_id'        => $staff->branch_id,
            'role_name'        => $staff->role->name,
            'currency'         => $staff->tenant?->currency,
            'plan'             => $staff->tenant?->activeSubscription?->plan?->code,
            'permissions'      => $staff->role->permissions->pluck('code')->toArray(),
        ]));
    }

    // Refresh an (possibly expired-but-refreshable) JWT for a new one.
    // NOTE: deliberately NOT behind the 'jwt.auth' middleware — that
    // middleware rejects expired tokens outright, which would make this
    // endpoint unreachable in the one case it exists for.
    public function refresh()
    {
        try {
            $newToken = JWTAuth::parseToken()->refresh();
        } catch (JWTException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Could not refresh token, please log in again',
            ], 401);
        }

        return response()->json([
            'status'     => 'success',
            'token'      => $newToken,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
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

    // ─────────────────────────────────────────────────────────────────────────────
    // CHANGE PASSWORD — self-service, requires the current password.
    // PUT /api/v1/auth/password
    // Body: { "current_password": "...", "new_password": "...", "new_password_confirmation": "..." }
    // ─────────────────────────────────────────────────────────────────────────────
    public function changePassword(Request $request, PasswordService $passwordService)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'      => array_merge(PasswordPolicy::rules(), ['confirmed']),
        ]);

        $user = JWTAuth::parseToken()->authenticate();

        if (!Hash::check($request->current_password, $user->password_hash)) {
            return response()->json([
                'status'  => 'invalid_current_password',
                'message' => 'Current password is incorrect',
            ], 401);
        }

        $passwordService->applyPassword($user, $request->new_password, temporary: false);

        ActivityLog::log(
            action: 'auth.password_changed',
            entity: null,
            payload: null,
            description: "User {$user->email} changed their password"
        );

        return response()->json(['status' => 'success', 'message' => 'Password updated successfully.']);
    }
}
