<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantSubscription;
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

    public function test(): \Illuminate\Http\JsonResponse
    {
        return response()->json(['message' => 'API is working']);
    }

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


    public function me(Request $request, \App\Services\NotificationService $notifications)
    {
        // ── jwt.auth middleware already authenticated this request —
        // reuse its resolved user instead of re-parsing the token ────────────
        $user = auth()->user();

        // Common shape returned for every branch below; each branch only
        // overrides what actually differs, so the frontend never has to
        // guess whether a key is simply missing vs. genuinely null.
        $base = [
            'user'              => $this->meUserShape($user),
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
            // Only ever set for a Staff row (see branch 3 below) — owners
            // and super admins have no Staff record of their own, so a
            // cash-drawer session (which is staff_id-scoped) can't be
            // opened "as" them; the app bar's register status is read-only
            // for those roles.
            'staff_id'          => null,
            'role_name'         => null,
            'permissions'       => [],
            'features'          => [],
            'subscription_status' => null,
            'trial_ends_at'       => null,
            // EnsureSubscriptionActive's other block condition (Tenant.is_active
            // === false) — /me is deliberately outside that middleware's gate,
            // so this is the only reliable way the frontend can tell "am I
            // blocked" on page load/refresh without waiting for some other
            // request to happen to 403 first. true by default (not blocked) —
            // a super admin has no tenant of their own to be suspended.
            'tenant_is_active'    => true,
            'must_change_password' => $user->must_change_password,
            // Layout.vue's notification-bell badge reads this — was always
            // 0 because this key was never set at all. NotificationService
            // already has the correct "visible to this user" query (accounts
            // for direct user_id targeting as well as role_id/branch_id
            // broadcasts), reused as-is rather than duplicating that logic here.
            'unread_notifications_count' => $notifications->unreadCount($user),
        ];

        // ── 1. Super Admin ─────────────────────────────────────────
        // No tenant, no branch, no role — bypasses everything
        if ($user->is_super_admin) {
            return response()->json(array_merge($base, [
                'is_super_admin' => true,
                'permissions'    => Permission::allCodesCached(),
                'features'       => Feature::allCodesCached(),
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

            $subscription = $this->lazilyExpireTrial($ownedTenant->activeSubscription);

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
                // Not tied to one branch — the union across every branch this
                // tenant owns, so nav stays visible if ANY branch supports a
                // feature. Real enforcement for a specific branch still goes
                // through the feature:CODE route middleware.
                'features'         => Feature::codesForTenant($ownedTenant->id),
                'subscription_status' => $subscription?->status,
                'trial_ends_at'       => $subscription?->trial_ends_at,
                'tenant_is_active'    => $ownedTenant->is_active,
            ]));
        }

        // ── 3. Regular Staff ───────────────────────────────────────────────
        $staff = $user->staff()
            ->withoutGlobalScopes()          // ← bypass TenantScope
            ->with(['role.permissions', 'branch.branchType', 'tenant.businessType', 'tenant.activeSubscription.plan'])
            ->first();

        if (!$staff) {
            return response()->json(['error' => 'No active staff record'], 403);
        }

        $subscription = $this->lazilyExpireTrial($staff->tenant?->activeSubscription);

        return response()->json(array_merge($base, [
            'is_staff'         => true,
            'tenant_id'        => $staff->tenant_id,
            'bu_name'          => $staff->tenant?->name,
            'business_type_id' => $staff->tenant?->business_type_id,
            'bu_type'          => $staff->tenant?->businessType?->code,
            'logo_url'         => $staff->tenant?->logo_url,
            'branch_name'      => $staff->branch?->name,
            'branch_id'        => $staff->branch_id,
            'staff_id'         => $staff->id,
            'role_name'        => $staff->role->name,
            'currency'         => $staff->tenant?->currency,
            'plan'             => $staff->tenant?->activeSubscription?->plan?->code,
            'permissions'      => $staff->role->permissions->pluck('code')->toArray(),
            'features'         => $staff->branch?->branch_type_id
                ? Feature::codesForBranchType($staff->branch->branch_type_id)
                : [],
            'subscription_status' => $subscription?->status,
            'trial_ends_at'       => $subscription?->trial_ends_at,
            'tenant_is_active'    => $staff->tenant?->is_active ?? true,
        ]));
    }

    // A plain array, not the raw model — 'user' => $user used to dump every
    // column plus whatever relations happened to already be loaded on this
    // request's auth()->user() instance (e.g. ownedTenant, accessed a few
    // lines below and elsewhere), which serialized as a full nested tenant
    // object under user.owned_tenant. Trimmed to exactly what the frontend
    // reads from authStore.me: id/email/full_name/avatar_url (profile) and
    // two_factor_confirmed_at (security settings) — plus first_name/last_name,
    // which AppBar.vue's getInitials()/getAvatarColor() need split out (not
    // derivable from full_name alone without re-parsing it).
    private function meUserShape(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'avatar_url' => $user->avatar_url,
            'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
        ];
    }

    // Lazy trial expiry — there's no cron/scheduler running in this app (see
    // TenantSubscriptionService), so a trial's end is only ever noticed the
    // next time its tenant's own users hit me(), which is every login and
    // every silent token refresh. Good enough: nothing meaningful happens on
    // an account nobody is actively using anyway.
    private function lazilyExpireTrial(?TenantSubscription $subscription): ?TenantSubscription
    {
        if ($subscription?->status === 'trial' && $subscription->trial_ends_at?->isPast()) {
            $subscription->update(['status' => 'suspended']);
        }

        return $subscription;
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
    // UPDATE EMAIL — self-service, requires the current password (email
    // doubles as the account's own password-reset/recovery channel, so
    // changing it deserves the same identity check as changing the
    // password itself).
    // PUT /api/v1/auth/email
    // Body: { "email": "...", "current_password": "..." }
    // ─────────────────────────────────────────────────────────────────────────────
    public function updateEmail(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $request->validate([
            'email'            => 'required|email|unique:users,email,' . $user->id,
            'current_password' => 'required|string',
        ]);

        if (!Hash::check($request->current_password, $user->password_hash)) {
            return response()->json([
                'status'  => 'invalid_current_password',
                'message' => 'Current password is incorrect',
            ], 401);
        }

        $oldEmail = $user->email;

        // A changed email is a fresh, unverified identity claim — reset
        // verified_at rather than trusting that whoever typed the new
        // address actually owns it, exactly as a brand-new signup would be.
        $user->forceFill(['email' => $request->email, 'email_verified_at' => null])->save();

        ActivityLog::log(
            action: 'auth.email_changed',
            entity: null,
            payload: null,
            description: "User changed their email from {$oldEmail} to {$user->email}"
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Email updated successfully.',
            'email'   => $user->email,
        ]);
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
