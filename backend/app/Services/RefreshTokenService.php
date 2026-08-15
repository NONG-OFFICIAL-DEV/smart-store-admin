<?php

namespace App\Services;

use App\Exceptions\InvalidRefreshTokenException;
use App\Exceptions\RefreshTokenExpiredException;
use App\Exceptions\RefreshTokenReusedException;
use App\Models\ActivityLog;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class RefreshTokenService
{
    /**
     * Issues a brand-new token family — call this only for a fresh login,
     * never for a rotation (see rotate(), which keeps the existing family).
     */
    public function issue(User $user, Request $request): array
    {
        return $this->createRow($user->id, (string) Str::uuid(), $request);
    }

    /**
     * Validates, rotates, and returns a fresh access+refresh pair.
     *
     * Reuse of an already-revoked token is treated as a compromise signal —
     * the entire family (every token descended from that one login) is
     * revoked immediately, forcing that session (and only that session;
     * other devices have their own family_id) to fully re-login.
     */
    public function rotate(string $rawToken, Request $request): array
    {
        $hash = hash('sha256', $rawToken);

        $stored = RefreshToken::where('token_hash', $hash)->first();

        if (! $stored) {
            throw new InvalidRefreshTokenException();
        }

        if ($stored->revoked_at !== null) {
            $this->revokeFamily($stored->family_id);

            ActivityLog::log(
                action: 'auth.refresh_reuse_detected',
                entity: $stored->user,
                payload: null,
                description: "Refresh token reuse detected for user {$stored->user_id} — session family revoked."
            );

            throw new RefreshTokenReusedException();
        }

        if ($stored->expires_at->isPast()) {
            throw new RefreshTokenExpiredException();
        }

        $stored->update(['revoked_at' => now(), 'last_used_at' => now()]);

        $new = $this->createRow($stored->user_id, $stored->family_id, $request);

        return [
            ...$new,
            'access_token' => JWTAuth::fromUser($stored->user),
        ];
    }

    /**
     * Single-session revoke — used by logout(). Deliberately scoped to only
     * the one token presented, never the user's other sessions/devices.
     */
    public function revoke(string $rawToken): void
    {
        RefreshToken::where('token_hash', hash('sha256', $rawToken))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * Bulk revoke — used after a password change/reset, where every
     * existing session should be forced to re-authenticate.
     */
    public function revokeAllForUser(string $userId): void
    {
        RefreshToken::where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    private function revokeFamily(string $familyId): void
    {
        RefreshToken::where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    private function createRow(string $userId, string $familyId, Request $request): array
    {
        $raw = Str::random(64);

        RefreshToken::create([
            'user_id' => $userId,
            'family_id' => $familyId,
            'token_hash' => hash('sha256', $raw),
            'device_name' => substr((string) $request->userAgent(), 0, 255),
            'ip_address' => $request->ip(),
            'expires_at' => now()->addMinutes(config('refresh_tokens.ttl')),
        ]);

        return [
            'refresh_token' => $raw,
            'refresh_expires_in' => config('refresh_tokens.ttl') * 60,
        ];
    }
}
