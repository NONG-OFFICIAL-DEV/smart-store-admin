<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TwoFactorAuthService
{
    public function __construct(private Google2FA $google2fa)
    {
    }

    public function hasTwoFactorEnabled(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null;
    }

    /**
     * Starts (or restarts) enrollment — a fresh secret wipes any prior
     * confirmation, so generating a new QR code always requires confirming
     * a code against THIS secret before 2FA is considered active again.
     */
    public function generateSecret(User $user): array
    {
        $secret = $this->google2fa->generateSecretKey();

        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $otpauthUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        return [
            'secret' => $secret,
            // ->generate() returns an Illuminate\Support\HtmlString, not a
            // plain string — left uncast, it JSON-encodes to {} (no public
            // properties) and the frontend's v-html then renders the
            // literal text "[object Object]" instead of the QR code.
            'qr_code_svg' => (string) QrCode::size(200)->generate($otpauthUrl),
        ];
    }

    /**
     * Verifies the just-generated secret's code, enables 2FA, and returns
     * a fresh set of recovery codes in PLAINTEXT — the only time they're
     * ever readable, since they're stored hashed from this point on.
     */
    public function confirm(User $user, string $code): array
    {
        if (! $user->two_factor_secret || ! $this->google2fa->verifyKey($user->two_factor_secret, $code)) {
            throw new \InvalidArgumentException('Invalid verification code.');
        }

        $plainRecoveryCodes = $this->generateRecoveryCodes();

        $user->update([
            'two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $plainRecoveryCodes),
            'two_factor_confirmed_at' => now(),
        ]);

        return $plainRecoveryCodes;
    }

    /**
     * Tries a TOTP code first, then falls back to recovery codes. A
     * matched recovery code is removed from the array immediately —
     * single-use.
     */
    public function verifyCode(User $user, string $code): bool
    {
        if ($user->two_factor_secret && $this->google2fa->verifyKey($user->two_factor_secret, $code)) {
            return true;
        }

        $recoveryCodes = $user->two_factor_recovery_codes ?? [];

        foreach ($recoveryCodes as $index => $hashedCode) {
            if (Hash::check($code, $hashedCode)) {
                unset($recoveryCodes[$index]);
                $user->update(['two_factor_recovery_codes' => array_values($recoveryCodes)]);

                return true;
            }
        }

        return false;
    }

    public function disable(User $user): void
    {
        $user->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    private function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn () => Str::upper(Str::random(4)) . '-' . Str::upper(Str::random(4)))
            ->all();
    }
}
