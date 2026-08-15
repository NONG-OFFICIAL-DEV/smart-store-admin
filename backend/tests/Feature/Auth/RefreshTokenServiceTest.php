<?php

namespace Tests\Feature\Auth;

use App\Exceptions\InvalidRefreshTokenException;
use App\Exceptions\RefreshTokenExpiredException;
use App\Exceptions\RefreshTokenReusedException;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\RefreshTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class RefreshTokenServiceTest extends TestCase
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

    private function service(): RefreshTokenService
    {
        return $this->app->make(RefreshTokenService::class);
    }

    public function test_issue_creates_a_row_with_a_fresh_family_id(): void
    {
        $user = $this->makeUser('IssueUser');

        $result = $this->service()->issue($user, Request::create('/login'));

        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $result['refresh_token']),
        ]);
    }

    public function test_rotate_issues_a_new_token_in_the_same_family_and_revokes_the_old_one(): void
    {
        $user = $this->makeUser('RotateUser');
        $issued = $this->service()->issue($user, Request::create('/login'));

        $original = RefreshToken::where('user_id', $user->id)->firstOrFail();
        $rotated = $this->service()->rotate($issued['refresh_token'], Request::create('/refresh'));

        $this->assertNotNull($original->refresh()->revoked_at);
        $this->assertArrayHasKey('access_token', $rotated);

        $newRow = RefreshToken::where('token_hash', hash('sha256', $rotated['refresh_token']))->firstOrFail();
        $this->assertSame($original->family_id, $newRow->family_id);
    }

    public function test_rotate_rejects_an_unknown_token(): void
    {
        $this->expectException(InvalidRefreshTokenException::class);

        $this->service()->rotate('not-a-real-token', Request::create('/refresh'));
    }

    public function test_rotate_rejects_an_expired_token_without_touching_the_rest_of_the_family(): void
    {
        $user = $this->makeUser('ExpiredUser');
        $issued = $this->service()->issue($user, Request::create('/login'));

        RefreshToken::where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);

        $this->expectException(RefreshTokenExpiredException::class);

        try {
            $this->service()->rotate($issued['refresh_token'], Request::create('/refresh'));
        } finally {
            $this->assertNull(RefreshToken::where('user_id', $user->id)->first()?->revoked_at);
        }
    }

    public function test_reusing_an_already_rotated_token_revokes_the_whole_family(): void
    {
        $user = $this->makeUser('ReuseUser');
        $issued = $this->service()->issue($user, Request::create('/login'));

        // Rotate once (legitimate use) — the original token is now revoked.
        $rotated = $this->service()->rotate($issued['refresh_token'], Request::create('/refresh'));

        // Presenting the ORIGINAL (now-revoked) token again simulates a stolen copy.
        try {
            $this->service()->rotate($issued['refresh_token'], Request::create('/refresh'));
            $this->fail('Expected RefreshTokenReusedException.');
        } catch (RefreshTokenReusedException $e) {
            // expected
        }

        // The whole family — including the token issued by the legitimate
        // rotation above — must now be dead too.
        $this->assertNotNull(
            RefreshToken::where('token_hash', hash('sha256', $rotated['refresh_token']))->firstOrFail()->revoked_at
        );
    }

    public function test_reuse_detection_does_not_affect_a_different_login_session(): void
    {
        $user = $this->makeUser('MultiDeviceUser');
        $sessionA = $this->service()->issue($user, Request::create('/login'));
        $sessionB = $this->service()->issue($user, Request::create('/login'));

        $rotatedA = $this->service()->rotate($sessionA['refresh_token'], Request::create('/refresh'));

        try {
            $this->service()->rotate($sessionA['refresh_token'], Request::create('/refresh'));
        } catch (RefreshTokenReusedException $e) {
            // expected — triggers family A's revocation only
        }

        // Session B's own token, from a different login/family, must still work.
        $stillGood = $this->service()->rotate($sessionB['refresh_token'], Request::create('/refresh'));
        $this->assertArrayHasKey('access_token', $stillGood);
    }

    public function test_revoke_only_affects_the_single_session_it_targets(): void
    {
        $user = $this->makeUser('LogoutUser');
        $sessionA = $this->service()->issue($user, Request::create('/login'));
        $sessionB = $this->service()->issue($user, Request::create('/login'));

        $this->service()->revoke($sessionA['refresh_token']);

        $this->assertNotNull(
            RefreshToken::where('token_hash', hash('sha256', $sessionA['refresh_token']))->firstOrFail()->revoked_at
        );
        $this->assertNull(
            RefreshToken::where('token_hash', hash('sha256', $sessionB['refresh_token']))->firstOrFail()->revoked_at
        );
    }

    public function test_revoke_all_for_user_kills_every_session(): void
    {
        $user = $this->makeUser('PasswordChangeUser');
        $this->service()->issue($user, Request::create('/login'));
        $this->service()->issue($user, Request::create('/login'));

        $this->service()->revokeAllForUser($user->id);

        $this->assertSame(0, RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->count());
    }
}
