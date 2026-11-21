<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\AuthController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * My account > Profile tab — name/phone/avatar only. Email is changed
 * separately (updateEmail(), requires current password), so this never
 * touches it — see also PUT /v1/auth/profile's own routing comment.
 */
class UpdateProfileTest extends TestCase
{
    use RefreshDatabase;

    private function callUpdateProfile(array $payload, array $files = [])
    {
        $request = Request::create('/v1/auth/profile', 'PUT', $payload, [], $files);

        return $this->app->make(AuthController::class)->updateProfile($request);
    }

    public function test_updates_name_and_phone(): void
    {
        $user = User::create(['email' => 'u1@example.test', 'first_name' => 'Old', 'last_name' => 'Name']);
        Auth::login($user);

        $response = $this->callUpdateProfile([
            'first_name' => 'New',
            'last_name' => 'Name',
            'phone' => '012345678',
        ]);
        $payload = json_decode($response->getContent(), true);

        $user->refresh();
        $this->assertTrue($payload['success']);
        $this->assertSame('New', $user->first_name);
        $this->assertSame('012345678', $user->phone);
    }

    public function test_uploading_an_avatar_stores_it_and_sets_avatar_url(): void
    {
        Storage::fake('public');

        $user = User::create(['email' => 'u2@example.test', 'first_name' => 'A', 'last_name' => 'B']);
        Auth::login($user);

        $file = UploadedFile::fake()->image('avatar.png', 200, 200);

        $response = $this->callUpdateProfile(
            ['first_name' => 'A', 'last_name' => 'B'],
            ['avatar' => $file]
        );
        $payload = json_decode($response->getContent(), true);

        $this->assertStringContainsString('/storage/avatars/', $payload['data']['user']['avatar_url']);

        $storedPath = 'avatars/'.basename($payload['data']['user']['avatar_url']);
        Storage::disk('public')->assertExists($storedPath);
    }

    public function test_email_is_never_touched_by_this_endpoint(): void
    {
        $user = User::create(['email' => 'unchanged@example.test', 'first_name' => 'A', 'last_name' => 'B']);
        Auth::login($user);

        $this->callUpdateProfile(['first_name' => 'A', 'last_name' => 'B']);

        $user->refresh();
        $this->assertSame('unchanged@example.test', $user->email);
    }

    public function test_first_name_is_required(): void
    {
        $user = User::create(['email' => 'u3@example.test', 'first_name' => 'A', 'last_name' => 'B']);
        Auth::login($user);

        $this->expectException(ValidationException::class);
        $this->callUpdateProfile(['first_name' => '', 'last_name' => 'B']);
    }
}
