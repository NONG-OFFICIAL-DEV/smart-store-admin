<?php

namespace App\Services\Auth;

use App\Models\RefreshToken;
use Illuminate\Support\Str;

class RefreshTokenService
{
    private const EXPIRES_DAYS = 30;

    public function issue(
        string $tenantId,
        string $actorType,
        string $actorId,
        string $terminalId
    ): string {
        $raw  = Str::random(64);
        $hash = hash('sha256', $raw);

        RefreshToken::create([
            'tenant_id'   => $tenantId,
            'actor_type'  => $actorType,
            'actor_id'    => $actorId,
            'terminal_id' => $terminalId,
            'token_hash'  => $hash,
            'expires_at'  => now()->addDays(self::EXPIRES_DAYS),
        ]);

        return $raw; // returned to client once, never stored raw
    }

    /**
     * Verify, rotate, and return new refresh token.
     * Old token is revoked — replay attack prevention.
     */
    public function rotate(string $rawToken, string $terminalId): array
    {
        $hash  = hash('sha256', $rawToken);
        $token = RefreshToken::where('token_hash', $hash)
            ->where('terminal_id', $terminalId)
            ->where('is_revoked', false)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        // Revoke old token immediately
        $token->update(['is_revoked' => true, 'rotated_at' => now()]);

        // Issue new refresh token
        $newRaw = $this->issue(
            $token->tenant_id,
            $token->actor_type,
            $token->actor_id,
            $terminalId
        );

        return [
            'actor_type'    => $token->actor_type,
            'actor_id'      => $token->actor_id,
            'tenant_id'     => $token->tenant_id,
            'refresh_token' => $newRaw,
        ];
    }

    public function revokeAllForActor(string $actorType, string $actorId): void
    {
        RefreshToken::where('actor_type', $actorType)
            ->where('actor_id', $actorId)
            ->update(['is_revoked' => true]);
    }
}
