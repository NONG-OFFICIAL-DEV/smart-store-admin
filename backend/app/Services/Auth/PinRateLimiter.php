<?php

namespace App\Services\Auth;

use App\Models\PinAttempt;
use App\Exceptions\PinLockedException;

class PinRateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    public function check(string $tenantId, string $branchId, string $terminalId): void
    {
        $record = PinAttempt::where('branch_id', $branchId)
            ->where('terminal_id', $terminalId)
            ->first();

        if (!$record) return;

        if ($record->locked_until && now()->lt($record->locked_until)) {
            $remaining = now()->diffInMinutes($record->locked_until, false) + 1;
            throw new PinLockedException(
                "Too many failed attempts. Terminal locked for {$remaining} more minute(s)."
            );
        }

        // Auto-clear expired lockout
        if ($record->locked_until && now()->gte($record->locked_until)) {
            $record->update(['fail_count' => 0, 'locked_until' => null]);
        }
    }

    public function recordFailure(string $tenantId, string $branchId, string $terminalId): void
    {
        $record = PinAttempt::firstOrCreate(
            ['branch_id' => $branchId, 'terminal_id' => $terminalId],
            ['tenant_id' => $tenantId]
        );

        $record->fail_count++;
        $record->last_attempt_at = now();

        if ($record->fail_count >= self::MAX_ATTEMPTS) {
            $record->locked_until = now()->addMinutes(self::LOCKOUT_MINUTES);
            // TODO: fire event to notify owner
            // event(new TerminalLockedOut($tenantId, $branchId, $terminalId));
        }

        $record->save();
    }

    public function recordSuccess(string $branchId, string $terminalId): void
    {
        PinAttempt::where('branch_id', $branchId)
            ->where('terminal_id', $terminalId)
            ->update(['fail_count' => 0, 'locked_until' => null, 'last_attempt_at' => now()]);
    }
}
