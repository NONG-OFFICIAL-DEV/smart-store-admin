<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\AuthController;
use App\Models\Tenant;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * me()'s 'user' key used to be the raw Eloquent model — every column plus
 * whatever relations happened to already be hydrated on this request's
 * auth()->user() instance (e.g. ownedTenant, accessed elsewhere in me()
 * itself), which serialized as a full nested tenant object under
 * user.owned_tenant. Trimmed to exactly what authStore.me actually reads.
 */
class MeEndpointShapeTest extends TestCase
{
    use RefreshDatabase;

    private function callMe(): array
    {
        $response = $this->app->make(AuthController::class)->me(
            Request::create('/me', 'GET'),
            $this->app->make(NotificationService::class),
        );

        return json_decode($response->getContent(), true);
    }

    public function test_owner_user_shape_is_trimmed_and_does_not_leak_owned_tenant(): void
    {
        $owner = User::create([
            'email' => 'owner@example.test', 'first_name' => 'Own', 'last_name' => 'Er',
        ]);
        Tenant::create(['name' => 'T', 'slug' => 't-1', 'owner_user_id' => $owner->id]);
        Auth::login($owner);

        $body = $this->callMe();

        $this->assertSame(
            ['id', 'email', 'first_name', 'last_name', 'full_name', 'avatar_url', 'two_factor_confirmed_at'],
            array_keys($body['user'])
        );
        $this->assertSame($owner->id, $body['user']['id']);
        $this->assertSame('Own', $body['user']['first_name']);
        $this->assertSame('Er', $body['user']['last_name']);
        $this->assertSame('Own Er', $body['user']['full_name']);
        $this->assertArrayNotHasKey('owned_tenant', $body['user']);
        $this->assertArrayNotHasKey('password_hash', $body['user']);
        $this->assertArrayNotHasKey('notify_email', $body['user']);
    }

    public function test_tenant_is_active_reflects_the_owners_tenant_flag(): void
    {
        $owner = User::create([
            'email' => 'suspended@example.test', 'first_name' => 'S', 'last_name' => 'O',
        ]);
        Tenant::create(['name' => 'T', 'slug' => 't-2', 'owner_user_id' => $owner->id, 'is_active' => false]);
        Auth::login($owner);

        $body = $this->callMe();

        $this->assertFalse($body['tenant_is_active']);
    }

    public function test_tenant_is_active_defaults_true_for_a_super_admin(): void
    {
        $admin = User::create([
            'email' => 'admin@example.test', 'first_name' => 'A', 'last_name' => 'D', 'is_super_admin' => true,
        ]);
        Auth::login($admin);

        $body = $this->callMe();

        $this->assertTrue($body['tenant_is_active']);
    }
}
