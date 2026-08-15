<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;

class PasswordService
{
    // ── Generate a random password that satisfies PasswordPolicy::rules() ─────
    public function generate(int $length = 12): string
    {
        $upper  = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower  = 'abcdefghijkmnpqrstuvwxyz';
        $digits = '23456789';
        $all    = $upper . $lower . $digits;

        $password = $upper[random_int(0, strlen($upper) - 1)]
            . $lower[random_int(0, strlen($lower) - 1)]
            . $digits[random_int(0, strlen($digits) - 1)];

        for ($i = strlen($password); $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle($password);
    }

    // ── Set a user's password. $temporary=true also flags it for forced change ─
    public function applyPassword(User $user, string $plain, bool $temporary): void
    {
        $user->update([
            'password_hash'         => bcrypt($plain),
            'must_change_password'  => $temporary,
            'password_changed_at'   => $temporary ? null : now(),
        ]);
    }

    // ── Admin-initiated reset — generates (or accepts) a new temp password ────
    public function adminReset(User $user, ?string $newPlain = null): string
    {
        $plain = $newPlain ?? $this->generate();

        $this->applyPassword($user, $plain, temporary: true);

        ActivityLog::log(
            action: 'user.password_reset',
            entity: $user,
            payload: null,
            description: "Password reset for {$user->email}"
        );

        return $plain;
    }
}
