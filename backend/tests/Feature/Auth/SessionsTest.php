<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\AuthController;
use App\Models\User;
use App\Services\RefreshTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * My account > Security tab — one row per active (non-revoked, non-expired)
 * refresh token family. Rotation already revokes the previous row in a
 * family on every refresh, so this naturally shows one row per real device.
 */
class SessionsTest extends TestCase
{
    use RefreshDatabase;

    private function service(): RefreshTokenService
    {
        return $this->app->make(RefreshTokenService::class);
    }

    private function callSessions(array $payload = [])
    {
        $request = Request::create('/v1/auth/sessions', 'POST', $payload);

        return $this->app->make(AuthController::class)->sessions($request, $this->service());
    }

    private function callRevokeOthers(array $payload)
    {
        $request = Request::create('/v1/auth/sessions/revoke-others', 'POST', $payload);

        return $this->app->make(AuthController::class)->revokeOtherSessions($request, $this->service());
    }

    public function test_lists_active_sessions_and_marks_the_current_device(): void
    {
        $user = User::create(['email' => 'u1@example.test', 'first_name' => 'A', 'last_name' => 'B']);
        Auth::login($user);

        $deviceA = $this->service()->issue($user, Request::create('/', 'POST', [], [], [], ['HTTP_USER_AGENT' => 'Device A']));
        $this->service()->issue($user, Request::create('/', 'POST', [], [], [], ['HTTP_USER_AGENT' => 'Device B']));

        $response = $this->callSessions(['refresh_token' => $deviceA['refresh_token']]);
        $payload = json_decode($response->getContent(), true);

        $this->assertCount(2, $payload['data']['sessions']);
        $current = collect($payload['data']['sessions'])->firstWhere('is_current', true);
        $this->assertNotNull($current);
        $this->assertSame('Device A', $current['device_name']);
    }

    public function test_a_revoked_session_does_not_appear(): void
    {
        $user = User::create(['email' => 'u2@example.test', 'first_name' => 'A', 'last_name' => 'B']);
        Auth::login($user);

        $device = $this->service()->issue($user, Request::create('/', 'POST'));
        $this->service()->revoke($device['refresh_token']);

        $response = $this->callSessions();
        $payload = json_decode($response->getContent(), true);

        $this->assertCount(0, $payload['data']['sessions']);
    }

    public function test_revoke_others_keeps_the_current_device_and_signs_out_the_rest(): void
    {
        $user = User::create(['email' => 'u3@example.test', 'first_name' => 'A', 'last_name' => 'B']);
        Auth::login($user);

        $deviceA = $this->service()->issue($user, Request::create('/', 'POST'));
        $deviceB = $this->service()->issue($user, Request::create('/', 'POST'));

        $this->callRevokeOthers(['refresh_token' => $deviceA['refresh_token']]);

        $sessions = $this->service()->listActiveForUser($user->id);
        $this->assertCount(1, $sessions);

        // deviceA's own token still rotates fine (not revoked)...
        $this->service()->rotate($deviceA['refresh_token'], Request::create('/', 'POST'));
        // ...but deviceB's was revoked, so reuse of it is treated as a
        // compromise signal (see RefreshTokenService::rotate()'s reuse check).
        $this->expectException(\App\Exceptions\RefreshTokenReusedException::class);
        $this->service()->rotate($deviceB['refresh_token'], Request::create('/', 'POST'));
    }
}
