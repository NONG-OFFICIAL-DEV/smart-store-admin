<?php

namespace App\Http\Controllers;

use App\Exceptions\PinLockedException;
use App\Exceptions\TerminalNotTrustedException;
use App\Models\{ActivityLog, Permission, RefreshToken, Staff, Tenant, TerminalTrust, User};
use App\Services\Auth\{PinRateLimiter, RefreshTokenService, TerminalTrustService};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function __construct(
        private PinRateLimiter      $limiter,
        private TerminalTrustService $trustService,
        private RefreshTokenService  $refreshService,
    ) {}

    // ─────────────────────────────────────────────────────────────────
    // POST /api/auth/login  — full credential login
    // ─────────────────────────────────────────────────────────────────
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'       => 'required|email',
            'password'    => 'required|string',
            'terminal_id' => 'required|string|max:100',
            'device_name' => 'nullable|string|max:120',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['status' => 'invalid_credentials', 'message' => 'Email or password is incorrect'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['status' => 'account_inactive', 'message' => 'Account deactivated. Contact support.'], 403);
        }

        try {
            if (!$token = JWTAuth::attempt(['email' => $request->email, 'password' => $request->password])) {
                return response()->json(['status' => 'invalid_credentials', 'message' => 'Email or password is incorrect'], 401);
            }

            $user->update(['last_login_at' => now()]);
            $user = $user->fresh();

            // ── Resolve actor (owner or staff) ────────────────────────────
            [$actorType, $actorId, $branchId, $tenantId] = $this->resolveActor($user, $request->branch_id ?? null);

            // ── Register terminal as trusted ──────────────────────────────
            $this->trustService->register(
                tenantId: $tenantId,
                branchId: $branchId ?? $request->branch_id,
                actorType: $actorType,
                actorId: $actorId,
                terminalId: $request->terminal_id,
                deviceName: $request->device_name,
            );

            // ── Issue refresh token ───────────────────────────────────────
            $refreshToken = $this->refreshService->issue(
                $tenantId,
                $actorType,
                $actorId,
                $request->terminal_id
            );

            ActivityLog::log('auth.login', null, null, "User {$user->email} logged in");

            return response()->json([
                'status'        => 'success',
                'token'         => $token,
                'token_type'    => 'bearer',
                'expires_in'    => JWTAuth::factory()->getTTL() * 60,
                'refresh_token' => $refreshToken,
                ...$this->buildUserPayload($user, $actorType, $actorId),
            ]);
        } catch (JWTException $e) {
            return response()->json(['status' => 'error', 'message' => 'Could not create token'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // POST /api/auth/login-pin  — PIN login (trusted terminals only)
    // Body: { pin_code, branch_id, terminal_id }
    // ─────────────────────────────────────────────────────────────────
    public function loginByPin(Request $request): JsonResponse
    {
        $request->validate([
            'pin_code'    => 'required|string|min:4|max:6|regex:/^\d+$/',
            'branch_id'   => 'nullable|uuid|exists:branches,id',
            'terminal_id' => 'required|string|max:100',
        ]);

        $branchId   = $request->branch_id;
        $terminalId = $request->terminal_id;

        try {
            // ── 1. Rate limit ─────────────────────────────────────────────
            // Find tenant_id from branch for logging
            $branch   = \App\Models\Branch::findOrFail($branchId);
            $tenantId = $branch->tenant_id;

            $this->limiter->check($tenantId, $branchId, $terminalId);

            // ── 2. Terminal must be trusted ───────────────────────────────
            $trusts = $this->trustService->getTrustedActors($branchId, $terminalId);

            // ── 3. Try each trusted actor's PIN ───────────────────────────
            foreach ($trusts as $trust) {
                $actor    = $this->loadActorModel($trust->actor_type, $trust->actor_id);
                $pinHash  = $this->getPinHash($actor, $trust->actor_type);

                if ($pinHash && Hash::check($request->pin_code, $pinHash)) {
                    // ── Match found ───────────────────────────────────────
                    $this->limiter->recordSuccess($branchId, $terminalId);
                    $this->trustService->touchLastUsed($branchId, $terminalId, $trust->actor_id);

                    $user = $this->getUserFromActor($trust->actor_type, $actor);

                    if (!$user || !$user->is_active) {
                        return response()->json(['status' => 'account_inactive', 'message' => 'Account deactivated.'], 403);
                    }

                    $jwtToken = JWTAuth::fromUser($user);
                    $user->update(['last_login_at' => now()]);

                    // Rotate refresh token for this terminal
                    $refreshToken = $this->refreshService->issue(
                        $tenantId,
                        $trust->actor_type,
                        $trust->actor_id,
                        $terminalId
                    );

                    ActivityLog::log(
                        'auth.login_pin',
                        null,
                        null,
                        "PIN login: {$trust->actor_type} {$trust->actor_id} on terminal {$terminalId}"
                    );

                    return response()->json([
                        'status'        => 'success',
                        'token'         => $jwtToken,
                        'token_type'    => 'bearer',
                        'expires_in'    => JWTAuth::factory()->getTTL() * 60,
                        'refresh_token' => $refreshToken,
                        ...$this->buildUserPayload($user, $trust->actor_type, $trust->actor_id),
                    ]);
                }
            }

            // ── No match ──────────────────────────────────────────────────
            $this->limiter->recordFailure($tenantId, $branchId, $terminalId);

            return response()->json([
                'status'  => 'invalid_pin',
                'message' => 'PIN is incorrect.',
            ], 401);
        } catch (PinLockedException $e) {
            return response()->json(['status' => 'locked', 'message' => $e->getMessage()], 429);
        } catch (TerminalNotTrustedException $e) {
            return response()->json(['status' => 'terminal_not_trusted', 'message' => $e->getMessage()], 403);
        } catch (JWTException $e) {
            return response()->json(['status' => 'error', 'message' => 'Could not create token'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // POST /api/auth/refresh  — silent token refresh
    // Body: { refresh_token, terminal_id }
    // ─────────────────────────────────────────────────────────────────
    public function refresh(Request $request): JsonResponse
    {
        $request->validate([
            'refresh_token' => 'required|string',
            'terminal_id'   => 'required|string|max:100',
        ]);

        try {
            $rotated = $this->refreshService->rotate($request->refresh_token, $request->terminal_id);

            $actor = $this->loadActorModel($rotated['actor_type'], $rotated['actor_id']);
            $user  = $this->getUserFromActor($rotated['actor_type'], $actor);

            $token = JWTAuth::fromUser($user);

            return response()->json([
                'status'        => 'success',
                'token'         => $token,
                'token_type'    => 'bearer',
                'expires_in'    => JWTAuth::factory()->getTTL() * 60,
                'refresh_token' => $rotated['refresh_token'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'refresh_failed', 'message' => 'Session expired. Please login again.'], 401);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // PUT /api/auth/set-pin
    // Body: { pin_code, pin_code_confirmation }
    // ─────────────────────────────────────────────────────────────────
    public function setPin(Request $request): JsonResponse
    {
        $request->validate([
            'pin_code' => 'required|string|min:4|max:6|regex:/^\d+$/|confirmed',
        ]);

        $user = JWTAuth::parseToken()->authenticate();

        // Check it's not an all-same-digit PIN (1111, 2222...)
        if (preg_match('/^(\d)\1+$/', $request->pin_code)) {
            return response()->json(['status' => 'error', 'message' => 'PIN cannot be all the same digit.'], 422);
        }

        // Check it's not sequential (1234, 4321...)
        if ($this->isSequentialPin($request->pin_code)) {
            return response()->json(['status' => 'error', 'message' => 'PIN cannot be a simple sequence.'], 422);
        }

        // Try staff first, then owner
        $staff = $user->staff()->withoutGlobalScopes()->first();
        if ($staff) {
            $staff->update(['pin_code' => Hash::make($request->pin_code)]);
            return response()->json(['status' => 'success', 'message' => 'PIN updated.']);
        }

        $tenant = Tenant::where('owner_user_id', $user->id)->first();
        if ($tenant) {
            $tenant->update(['owner_pin_code' => Hash::make($request->pin_code)]);
            return response()->json(['status' => 'success', 'message' => 'PIN updated.']);
        }

        return response()->json(['status' => 'error', 'message' => 'No staff or owner record found.'], 404);
    }

    // ─────────────────────────────────────────────────────────────────
    // DELETE /api/auth/terminal/{terminal_id}  — revoke terminal trust
    // ─────────────────────────────────────────────────────────────────
    public function revokeTerminal(Request $request, string $terminalId): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();

        $tenant = Tenant::where('owner_user_id', $user->id)->firstOrFail();

        TerminalTrust::where('tenant_id', $tenant->id)
            ->where('terminal_id', $terminalId)
            ->update(['is_revoked' => true]);

        // Also revoke refresh tokens for this terminal
        RefreshToken::where('tenant_id', $tenant->id)
            ->where('terminal_id', $terminalId)
            ->update(['is_revoked' => true]);

        return response()->json(['status' => 'success', 'message' => 'Terminal revoked.']);
    }

    // ─────────────────────────────────────────────────────────────────
    // POST /api/auth/logout
    // ─────────────────────────────────────────────────────────────────
    public function logout(): JsonResponse
    {
        try {
            ActivityLog::log('auth.logout', null, null, 'User logged out');
            JWTAuth::parseToken()->invalidate();
            return response()->json(['message' => 'Logged out successfully']);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Failed to logout'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // GET /api/auth/me
    // ─────────────────────────────────────────────────────────────────
    public function me(Request $request): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();

        if ($user->is_super_admin) {
            return response()->json([
                'user'        => $user,
                'is_super_admin' => true,
                'is_owner'    => false,
                'tenant_id'   => null,
                'branch_id'   => null,
                'permissions' => Permission::pluck('code')->toArray(),
            ]);
        }

        $ownedTenant = Tenant::where('owner_user_id', $user->id)->first();
        if ($ownedTenant) {
            return response()->json([
                'user'        => $user,
                'is_owner'    => true,
                'tenant_id'   => $ownedTenant->id,
                'bu_name'     => $ownedTenant->name,
                'bu_type'     => $ownedTenant->bu_type,
                'logo_url'    => $ownedTenant->logo_url,
                'currency'    => $ownedTenant->currency,
                'locale'      => $ownedTenant->locale,
                'plan'        => $ownedTenant->plan,
                'branch_id'   => null,
                'permissions' => Permission::pluck('code')->toArray(),
            ]);
        }

        $staff = $user->staff()
            ->withoutGlobalScopes()
            ->with(['role.permissions', 'branch', 'tenant'])
            ->first();

        if (!$staff) {
            return response()->json(['error' => 'No active staff record'], 403);
        }

        return response()->json([
            'user'        => $user,
            'is_staff'    => true,
            'tenant_id'   => $staff->tenant_id,
            'bu_name'     => $staff->tenant?->name,
            'bu_type'     => $staff->tenant?->bu_type,
            'logo_url'    => $staff->tenant?->logo_url,
            'branch_name' => $staff->branch?->name,
            'branch_id'   => $staff->branch_id,
            'role_name'   => $staff->role?->name,
            'currency'    => $staff->tenant?->currency,
            'plan'        => $staff->tenant?->plan,
            'permissions' => $staff->role?->permissions->pluck('code')->toArray() ?? [],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────

    private function resolveActor(User $user, ?string $branchId): array
    {
        $tenant = Tenant::where('owner_user_id', $user->id)->first();
        if ($tenant) {
            return ['owner', $tenant->id, $branchId, $tenant->id];
        }

        $staff = $user->staff()->withoutGlobalScopes()->first();
        if ($staff) {
            return ['staff', $staff->id, $staff->branch_id, $staff->tenant_id];
        }

        throw new \RuntimeException('No actor found for user');
    }

    private function loadActorModel(string $type, string $id): mixed
    {
        return match ($type) {
            'staff' => Staff::withoutGlobalScopes()->find($id),
            'owner' => Tenant::find($id),
            default => null,
        };
    }

    private function getPinHash(mixed $actor, string $type): ?string
    {
        return match ($type) {
            'staff' => $actor?->pin_code,
            'owner' => $actor?->owner_pin_code,
            default => null,
        };
    }

    private function getUserFromActor(string $type, mixed $actor): ?User
    {
        return match ($type) {
            'staff' => $actor?->user,
            'owner' => User::find($actor?->owner_user_id),
            default => null,
        };
    }

    private function buildUserPayload(User $user, string $actorType, string $actorId): array
    {
        $isOwner = $actorType === 'owner';
        $isStaff = $actorType === 'staff';

        $payload = [
            'user' => [
                'id'            => $user->id,
                'email'         => $user->email,
                'first_name'    => $user->first_name,
                'last_name'     => $user->last_name,
                'full_name'     => $user->full_name,
                'avatar_url'    => $user->avatar_url,
                'is_active'     => $user->is_active,
                'last_login_at' => $user->last_login_at,
                'has_pin' => $this->actorHasPin($actorType, $actorId),
            ],
            'is_super_admin' => false,
            'is_owner'       => $isOwner,
            'is_staff'       => $isStaff,
        ];

        if ($isOwner) {
            $tenant = Tenant::find($actorId);
            $payload += [
                'tenant_id'   => $tenant->id,
                'bu_name'     => $tenant->name,
                'bu_type'     => $tenant->bu_type,
                'logo_url'    => $tenant->logo_url,
                'currency'    => $tenant->currency,
                'plan'        => $tenant->plan,
                'branch_id'   => null,
                'permissions' => Permission::pluck('code')->toArray(),
            ];
        }

        if ($isStaff) {
            $staff = Staff::withoutGlobalScopes()->with(['role.permissions', 'branch', 'tenant'])->find($actorId);
            $payload += [
                'tenant_id'   => $staff->tenant_id,
                'bu_name'     => $staff->tenant?->name,
                'bu_type'     => $staff->tenant?->bu_type,
                'logo_url'    => $staff->tenant?->logo_url,
                'branch_id'   => $staff->branch_id,
                'branch_name' => $staff->branch?->name,
                'role_name'   => $staff->role?->name,
                'currency'    => $staff->tenant?->currency,
                'plan'        => $staff->tenant?->plan,
                'permissions' => $staff->role?->permissions->pluck('code')->toArray() ?? [],
            ];
        }

        return $payload;
    }

    private function isSequentialPin(string $pin): bool
    {
        $ascending  = implode('', range($pin[0], $pin[0] + strlen($pin) - 1));
        $descending = implode('', range($pin[0], $pin[0] - strlen($pin) + 1));
        return $pin === $ascending || $pin === $descending;
    }

    // Add helper method
    private function actorHasPin(string $actorType, string $actorId): bool
    {
        return match ($actorType) {
            'staff' => !is_null(Staff::withoutGlobalScopes()->find($actorId)?->pin_code),
            'owner' => !is_null(Tenant::find($actorId)?->owner_pin_code),
            default => false,
        };
    }
}
