<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name = 'Tester'): User
    {
        return User::create([
            'email' => strtolower($name) . '@example.test',
            'first_name' => $name,
            'last_name' => 'User',
        ]);
    }

    private function service(): TwoFactorAuthService
    {
        return $this->app->make(TwoFactorAuthService::class);
    }

    private function currentTotpFor(string $secret): string
    {
        return (new Google2FA())->getCurrentOtp($secret);
    }

    public function test_generate_secret_stores_a_secret_and_leaves_two_factor_unconfirmed(): void
    {
        $user = $this->makeUser('Setup');

        $result = $this->service()->generateSecret($user);

        $user->refresh();
        $this->assertNotEmpty($result['secret']);
        $this->assertNotEmpty($result['qr_code_svg']);
        $this->assertNotEmpty($user->two_factor_secret);
        $this->assertFalse($this->service()->hasTwoFactorEnabled($user));
    }

    public function test_confirm_with_a_valid_code_enables_two_factor_and_returns_recovery_codes(): void
    {
        $user = $this->makeUser('Confirm');
        $secret = $this->service()->generateSecret($user)['secret'];
        $user->refresh();

        $recoveryCodes = $this->service()->confirm($user, $this->currentTotpFor($secret));

        $user->refresh();
        $this->assertCount(8, $recoveryCodes);
        $this->assertTrue($this->service()->hasTwoFactorEnabled($user));
        // Stored codes are hashed, never the plaintext values themselves.
        $this->assertNotContains($recoveryCodes[0], $user->two_factor_recovery_codes);
    }

    public function test_confirm_with_an_invalid_code_does_not_enable_two_factor(): void
    {
        $user = $this->makeUser('BadConfirm');
        $this->service()->generateSecret($user);
        $user->refresh();

        $this->expectException(\InvalidArgumentException::class);

        try {
            $this->service()->confirm($user, '000000');
        } finally {
            $this->assertFalse($this->service()->hasTwoFactorEnabled($user->fresh()));
        }
    }

    public function test_verify_code_accepts_a_valid_totp_code(): void
    {
        $user = $this->makeUser('Verify');
        $secret = $this->service()->generateSecret($user)['secret'];
        $user->refresh();
        $this->service()->confirm($user, $this->currentTotpFor($secret));
        $user->refresh();

        $this->assertTrue($this->service()->verifyCode($user, $this->currentTotpFor($secret)));
    }

    public function test_verify_code_rejects_a_wrong_code(): void
    {
        $user = $this->makeUser('WrongCode');
        $secret = $this->service()->generateSecret($user)['secret'];
        $user->refresh();
        $this->service()->confirm($user, $this->currentTotpFor($secret));
        $user->refresh();

        $this->assertFalse($this->service()->verifyCode($user, '000000'));
    }

    public function test_verify_code_accepts_a_recovery_code_exactly_once(): void
    {
        $user = $this->makeUser('Recovery');
        $secret = $this->service()->generateSecret($user)['secret'];
        $user->refresh();
        $recoveryCodes = $this->service()->confirm($user, $this->currentTotpFor($secret));
        $user->refresh();

        $code = $recoveryCodes[0];

        $this->assertTrue($this->service()->verifyCode($user, $code));
        $this->assertFalse($this->service()->verifyCode($user->fresh(), $code));
    }

    public function test_disable_clears_all_two_factor_columns(): void
    {
        $user = $this->makeUser('Disable');
        $secret = $this->service()->generateSecret($user)['secret'];
        $user->refresh();
        $this->service()->confirm($user, $this->currentTotpFor($secret));
        $user->refresh();

        $this->service()->disable($user);

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertFalse($this->service()->hasTwoFactorEnabled($user));
    }
}
