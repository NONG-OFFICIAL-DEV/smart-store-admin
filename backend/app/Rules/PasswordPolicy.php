<?php

namespace App\Rules;

use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    // ── Shared password requirements used by every account-creating flow ──────
    // (User, Tenant owner, Staff) and by self-service change-password.
    public static function rules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'sometimes',
            'string',
            Password::min(8)->mixedCase()->numbers(),
        ];
    }
}
