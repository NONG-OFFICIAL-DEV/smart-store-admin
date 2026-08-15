<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\ActivityLog;
use App\Rules\PasswordPolicy;
use App\Services\PasswordService;
use App\Services\RefreshTokenService;
use App\Services\TwoFactorAuthService;
use App\Exceptions\InvalidRefreshTokenException;
use App\Exceptions\RefreshTokenExpiredException;
use App\Exceptions\RefreshTokenReusedException;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use App\Models\User;

class AuthController extends Controller
{
    private const TWO_FACTOR_CHALLENGE_TTL_MINUTES = 5;

    // Login user
    public function login(
        Request $request,
        RefreshTokenService $refreshTokenService,
        TwoFactorAuthService $twoFactor
    ) {
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

            // Password verification succeeded, but that is NOT the same as
            // "fully authenticated" for a 2FA-enabled account — the token
            // attempt() just minted is invalidated immediately and never
            // sent to the client. A real token is only issued once the
            // second factor is verified (see verifyTwoFactor()).
            if ($twoFactor->hasTwoFactorEnabled($user)) {
                JWTAuth::setToken($token)->invalidate();

                $challengeToken = Str::random(40);
                Cache::put(
                    "two_factor_challenge:{$challengeToken}",
                    $user->id,
                    now()->addMinutes(self::TWO_FACTOR_CHALLENGE_TTL_MINUTES)
                );

                return response()->json([
                    'status' => 'success',
                    'requires_two_factor' => true,
                    'two_factor_token' => $challengeToken,
                ]);
            }

            return $this->buildLoginResponse($user, $token, $refreshTokenService, $request);
        } catch (JWTException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Could not create token, please try again',
            ], 500);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // Second step of a 2FA-gated login — verifies the challenge token +
    // code, and only then mints a real access+refresh token pair.
    public function verifyTwoFactor(
        Request $request,
        RefreshTokenService $refreshTokenService,
        TwoFactorAuthService $twoFactor
    ) {
        $request->validate([
            'two_factor_token' => 'required|string',
            'code' => 'required|string',
        ]);

        $userId = Cache::pull("two_factor_challenge:{$request->two_factor_token}");

        if (! $userId) {
            return response()->json([
                'status' => 'error',
                'code' => 'TWO_FACTOR_CHALLENGE_EXPIRED',
                'message' => 'This verification session has expired. Please log in again.',
            ], 422);
        }

        $user = User::find($userId);

        if (! $user || ! $twoFactor->verifyCode($user, $request->code)) {
            // Put the challenge back — a wrong code shouldn't force the
            // user all the way back to a fresh password login.
            Cache::put(
                "two_factor_challenge:{$request->two_factor_token}",
                $userId,
                now()->addMinutes(self::TWO_FACTOR_CHALLENGE_TTL_MINUTES)
            );

            return response()->json([
                'status' => 'error',
                'code' => 'INVALID_TWO_FACTOR_CODE',
                'message' => 'Invalid verification code.',
            ], 422);
        }

        $token = JWTAuth::fromUser($user);

        return $this->buildLoginResponse($user, $token, $refreshTokenService, $request);
    }

    private function buildLoginResponse(
        User $user,
        string $token,
        RefreshTokenService $refreshTokenService,
        Request $request
    ) {
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

        $refreshToken = $refreshTokenService->issue($user, $request);

        return response()->json([
            'status'     => 'success',
            'token'      => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            ...$refreshToken,
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

    // Exchanges a refresh token for a brand-new access+refresh pair.
    // NOTE: deliberately NOT behind the 'jwt.auth' middleware — this
    // endpoint never looks at the (possibly long-expired) access token at
    // all, only the refresh token in the body, so there's no JWT for that
    // middleware to validate in the first place.
    public function refresh(Request $request, RefreshTokenService $refreshTokenService)
    {
        $request->validate(['refresh_token' => 'required|string']);

        try {
            $result = $refreshTokenService->rotate($request->refresh_token, $request);
        } catch (InvalidRefreshTokenException|RefreshTokenExpiredException|RefreshTokenReusedException $e) {
            return response()->json([
                'status'  => 'session_revoked',
                'message' => $e->getMessage(),
            ], 401);
        }

        return response()->json([
            'status'             => 'success',
            'token'              => $result['access_token'],
            'token_type'         => 'bearer',
            'expires_in'         => JWTAuth::factory()->getTTL() * 60,
            'refresh_token'      => $result['refresh_token'],
            'refresh_expires_in' => $result['refresh_expires_in'],
        ]);
    }

    // Logout
    public function logout(Request $request, RefreshTokenService $refreshTokenService)
    {
        try {
            // ── Log before invalidating token ──────────────────────────────
            ActivityLog::log(
                action: 'auth.logout',
                entity: null,
                payload: null,
                description: 'User logged out'
            );

            // Scoped to just this session's own refresh token — logging out
            // on one device must never revoke another device's session.
            if ($request->filled('refresh_token')) {
                $refreshTokenService->revoke($request->refresh_token);
            }

            JWTAuth::parseToken()->invalidate();
            return response()->json(['message' => 'Logged out successfully']);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Failed to logout, token invalid'], 500);
        }
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
    public function changePassword(Request $request, PasswordService $passwordService, RefreshTokenService $refreshTokenService)
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

        // A password change is exactly the kind of event that should force
        // every OTHER session to re-authenticate — this device's own
        // current session keeps working (its access token isn't touched),
        // but a stolen refresh token from before the change is now dead.
        $refreshTokenService->revokeAllForUser($user->id);

        ActivityLog::log(
            action: 'auth.password_changed',
            entity: null,
            payload: null,
            description: "User {$user->email} changed their password"
        );

        return response()->json(['status' => 'success', 'message' => 'Password updated successfully.']);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // FORGOT PASSWORD — sends a reset link if the email exists. Deliberately
    // ALWAYS returns the same generic response regardless of whether the
    // broker actually found an account — never confirm/deny account
    // existence via a distinguishable response.
    // POST /api/forgot-password
    // Body: { "email": "..." }
    // ─────────────────────────────────────────────────────────────────────────────
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            // Rate-limit state isn't an account-existence leak — safe to
            // surface distinctly.
            return response()->json([
                'status'  => 'throttled',
                'message' => 'Please wait before requesting another reset link.',
            ], 429);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'If that email address is registered, a password reset link has been sent.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // RESET PASSWORD — completes the flow with the token from the emailed link.
    // POST /api/reset-password
    // Body: { "token": "...", "email": "...", "password": "...", "password_confirmation": "..." }
    // ─────────────────────────────────────────────────────────────────────────────
    public function resetPassword(Request $request, PasswordService $passwordService, RefreshTokenService $refreshTokenService)
    {
        $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => array_merge(PasswordPolicy::rules(), ['confirmed']),
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) use ($passwordService, $refreshTokenService) {
                $passwordService->applyPassword($user, $password, temporary: false);
                $refreshTokenService->revokeAllForUser($user->id);

                event(new PasswordReset($user));

                ActivityLog::log(
                    action: 'auth.password_reset',
                    entity: $user,
                    payload: null,
                    description: "Password reset via emailed link for {$user->email}"
                );
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            // Still no distinguishable-by-email response — an invalid
            // token and a token for a nonexistent email both fail
            // Password::reset() the same way.
            return response()->json([
                'status'  => 'invalid_token',
                'message' => 'This password reset link is invalid or has expired.',
            ], 422);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Password reset successfully. Please log in with your new password.',
        ]);
    }
}
