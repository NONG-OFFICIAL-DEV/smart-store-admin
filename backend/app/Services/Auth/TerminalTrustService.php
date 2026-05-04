<?php

namespace App\Services\Auth;

use App\Models\TerminalTrust;
use App\Exceptions\TerminalNotTrustedException;

class TerminalTrustService
{
    private const TRUST_DAYS = 30;

    public function register(
        string $tenantId,
        ?string $branchId,
        string $actorType,
        string $actorId,
        string $terminalId,
        ?string $deviceName = null
    ): TerminalTrust {
        return TerminalTrust::updateOrCreate(
            [
                'branch_id'   => $branchId,
                'terminal_id' => $terminalId,
                'actor_type'  => $actorType,
                'actor_id'    => $actorId,
            ],
            [
                'tenant_id'   => $tenantId,
                'device_name' => $deviceName,
                'trusted_at'  => now(),
                'expires_at'  => now()->addDays(self::TRUST_DAYS),
                'is_revoked'  => false,
            ]
        );
    }

    /**
     * Returns all valid trusted actors for this terminal.
     * Used during PIN login to know who to check PINs against.
     */
    public function getTrustedActors(string $branchId, string $terminalId): \Illuminate\Support\Collection
    {
        $trusts = TerminalTrust::where('branch_id', $branchId)
            ->where('terminal_id', $terminalId)
            ->where('is_revoked', false)
            ->where('expires_at', '>', now())
            ->get();

        if ($trusts->isEmpty()) {
            throw new TerminalNotTrustedException(
                'This terminal is not registered. Please login with your credentials first.'
            );
        }

        return $trusts;
    }

    public function touchLastUsed(string $branchId, string $terminalId, string $actorId): void
    {
        TerminalTrust::where('branch_id', $branchId)
            ->where('terminal_id', $terminalId)
            ->where('actor_id', $actorId)
            ->update(['last_used_at' => now()]);
    }

    public function revokeForActor(string $actorType, string $actorId): void
    {
        TerminalTrust::where('actor_type', $actorType)
            ->where('actor_id', $actorId)
            ->update(['is_revoked' => true]);
    }

    public function revokeAllForBranch(string $branchId): void
    {
        TerminalTrust::where('branch_id', $branchId)
            ->update(['is_revoked' => true]);
    }
}
