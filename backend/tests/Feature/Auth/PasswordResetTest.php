<?php

namespace Tests\Feature\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\PasswordService;
use App\Services\RefreshTokenService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
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

    public function test_reset_link_email_points_at_the_frontend_url(): void
    {
        config(['app.frontend_url' => 'https://admin.example.test']);
        Notification::fake();

        $user = $this->makeUser('LinkUser');
        Password::sendResetLink(['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $mail = $notification->toMail($user);
            $url = $mail->actionUrl;

            return str_starts_with($url, 'https://admin.example.test/reset-password?token=')
                && str_contains($url, 'email=' . urlencode($user->email));
        });
    }

    public function test_send_reset_link_succeeds_for_an_existing_email(): void
    {
        Notification::fake();
        $user = $this->makeUser('ExistingUser');

        $status = Password::sendResetLink(['email' => $user->email]);

        $this->assertSame(Password::RESET_LINK_SENT, $status);
    }

    public function test_send_reset_link_for_a_nonexistent_email_does_not_error_or_create_a_token(): void
    {
        Notification::fake();

        $status = Password::sendResetLink(['email' => 'nobody@example.test']);

        // Broker-level status differs (INVALID_USER) — AuthController's own
        // forgotPassword() maps BOTH this and RESET_LINK_SENT to the exact
        // same generic HTTP response, which is what actually matters for
        // not leaking account existence. Confirmed here: no token row
        // exists for an email that was never registered.
        $this->assertNotSame(Password::RESET_LINK_SENT, $status);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'nobody@example.test']);
    }

    public function test_reset_applies_the_new_password_and_revokes_every_existing_session(): void
    {
        $user = $this->makeUser('ResetUser');
        $passwordService = $this->app->make(PasswordService::class);
        $refreshTokenService = $this->app->make(RefreshTokenService::class);

        // Simulate an existing logged-in session before the reset.
        $refreshTokenService->issue($user, Request::create('/login'));
        $this->assertSame(1, RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->count());

        $token = Password::createToken($user);

        $status = Password::reset(
            ['email' => $user->email, 'password' => 'NewSecurePass123', 'password_confirmation' => 'NewSecurePass123', 'token' => $token],
            function ($resetUser, $password) use ($passwordService, $refreshTokenService) {
                $passwordService->applyPassword($resetUser, $password, temporary: false);
                $refreshTokenService->revokeAllForUser($resetUser->id);
            }
        );

        $this->assertSame(Password::PASSWORD_RESET, $status);

        $user->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewSecurePass123', $user->password_hash));
        $this->assertSame(0, RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->count());
    }

    public function test_reset_with_an_invalid_token_fails(): void
    {
        $user = $this->makeUser('BadTokenUser');

        $status = Password::reset(
            ['email' => $user->email, 'password' => 'NewSecurePass123', 'password_confirmation' => 'NewSecurePass123', 'token' => 'not-a-real-token'],
            fn () => null
        );

        $this->assertNotSame(Password::PASSWORD_RESET, $status);
    }

    public function test_reset_token_is_single_use(): void
    {
        $user = $this->makeUser('SingleUseUser');
        $token = Password::createToken($user);

        Password::reset(
            ['email' => $user->email, 'password' => 'FirstNewPass123', 'password_confirmation' => 'FirstNewPass123', 'token' => $token],
            fn ($resetUser, $password) => $this->app->make(PasswordService::class)->applyPassword($resetUser, $password, temporary: false)
        );

        // Reusing the same token a second time must fail.
        $status = Password::reset(
            ['email' => $user->email, 'password' => 'SecondNewPass123', 'password_confirmation' => 'SecondNewPass123', 'token' => $token],
            fn () => null
        );

        $this->assertNotSame(Password::PASSWORD_RESET, $status);
    }
}
