<?php

namespace Tests\Feature\Loyalty;

use App\Services\LoyaltyCardTokenService;
use Tests\TestCase;

class LoyaltyCardTokenServiceTest extends TestCase
{
    public function test_a_freshly_generated_token_resolves_to_the_same_customer(): void
    {
        $service = new LoyaltyCardTokenService();

        $token = $service->generate('customer-123');

        $this->assertSame('customer-123', $service->resolve($token));
    }

    public function test_a_token_more_than_one_window_old_is_rejected(): void
    {
        $service = new LoyaltyCardTokenService();

        $token = $this->tokenForWindow($service, 'customer-123', $this->currentWindow() - 2);

        $this->assertNull($service->resolve($token));
    }

    public function test_a_token_one_window_old_still_resolves(): void
    {
        $service = new LoyaltyCardTokenService();

        $token = $this->tokenForWindow($service, 'customer-123', $this->currentWindow() - 1);

        $this->assertSame('customer-123', $service->resolve($token));
    }

    public function test_a_tampered_signature_is_rejected(): void
    {
        $service = new LoyaltyCardTokenService();

        $token = $service->generate('customer-123');
        [$payload] = explode('.', $token, 2);
        $tampered = "{$payload}.0000000000000000";

        $this->assertNull($service->resolve($tampered));
    }

    public function test_a_token_for_a_different_customer_does_not_resolve_to_this_one(): void
    {
        $service = new LoyaltyCardTokenService();

        $tokenA = $service->generate('customer-a');
        [, $signatureA] = explode('.', $tokenA, 2);
        $tokenForwardedB = $this->rebuildWithPayload($service, 'customer-b', $this->currentWindow(), $signatureA);

        $this->assertNull($service->resolve($tokenForwardedB));
    }

    public function test_garbage_input_is_rejected(): void
    {
        $service = new LoyaltyCardTokenService();

        $this->assertNull($service->resolve('not-a-real-token'));
        $this->assertNull($service->resolve(''));
    }

    private function currentWindow(): int
    {
        return intdiv(time(), 60);
    }

    private function tokenForWindow(LoyaltyCardTokenService $service, string $customerId, int $window): string
    {
        $reflection = new \ReflectionClass($service);
        $sign = $reflection->getMethod('sign');
        $sign->setAccessible(true);

        return $sign->invoke($service, $customerId, $window);
    }

    private function rebuildWithPayload(LoyaltyCardTokenService $service, string $customerId, int $window, string $signature): string
    {
        $payload = "{$customerId}:{$window}";
        $encoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');

        return "{$encoded}.{$signature}";
    }
}
