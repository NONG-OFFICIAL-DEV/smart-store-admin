<?php

namespace App\Services;

/**
 * The customer-facing card QR encodes one of these instead of a bare
 * customer id — a screenshot of it stops resolving to anything after
 * ~60-120s, so it can't be reused at a different branch/visit. Stateless:
 * the window number and HMAC signature are both inside the token itself,
 * so no DB row/cache entry is needed to verify it.
 */
class LoyaltyCardTokenService
{
    private const WINDOW_SECONDS = 60;

    // Truncated to 16 hex chars (64 bits) — plenty for a token that's only
    // ever valid for ~60-120s, not a long-lived secret.
    private const SIGNATURE_LENGTH = 16;

    public function generate(string $customerId): string
    {
        return $this->sign($customerId, $this->currentWindow());
    }

    /**
     * Returns the customer id if the token is currently valid (±1 window,
     * i.e. ~60-120s old, to absorb scan latency/clock skew), null otherwise.
     */
    public function resolve(string $token): ?string
    {
        $parts = explode('.', $token, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$encodedPayload, $signature] = $parts;
        $payload = base64_decode(strtr($encodedPayload, '-_', '+/'), true);

        if ($payload === false) {
            return null;
        }

        [$customerId, $window] = array_pad(explode(':', $payload, 2), 2, null);

        if (! $customerId || $window === null || ! ctype_digit($window)) {
            return null;
        }

        $window = (int) $window;

        if (abs($this->currentWindow() - $window) > 1) {
            return null;
        }

        if (! hash_equals($this->signature($customerId, $window), $signature)) {
            return null;
        }

        return $customerId;
    }

    private function sign(string $customerId, int $window): string
    {
        $payload = "{$customerId}:{$window}";
        $encodedPayload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');

        return "{$encodedPayload}.{$this->signature($customerId, $window)}";
    }

    private function signature(string $customerId, int $window): string
    {
        return substr(hash_hmac('sha256', "{$customerId}:{$window}", config('app.key')), 0, self::SIGNATURE_LENGTH);
    }

    private function currentWindow(): int
    {
        return intdiv(time(), self::WINDOW_SECONDS);
    }
}
