<?php

namespace Tests\Feature\Notifications;

use App\Models\TelegramLinkToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramPollTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithOwner(string $name): array
    {
        $owner = User::create([
            'email' => strtolower($name).'@example.test',
            'first_name' => $name,
            'last_name' => 'Owner',
        ]);

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => strtolower($name).'-'.substr((string) $owner->id, 0, 8),
            'owner_user_id' => $owner->id,
        ]);

        return [$tenant, $owner];
    }

    public function test_skips_entirely_when_no_bot_token_is_configured(): void
    {
        config(['services.telegram.bot_token' => null]);
        Http::fake();

        $this->artisan('telegram:fetch-updates')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_a_valid_start_token_links_the_chat_to_the_right_user(): void
    {
        config(['services.telegram.bot_token' => 'fake-token']);
        [, $owner] = $this->makeTenantWithOwner('TenantA');

        $link = TelegramLinkToken::create([
            'user_id' => $owner->id,
            'token' => 'abc123',
            'expires_at' => now()->addMinutes(30),
        ]);

        Http::fake([
            'api.telegram.org/*/getUpdates*' => Http::response([
                'result' => [
                    [
                        'update_id' => 1,
                        'message' => [
                            'chat' => ['id' => 999888],
                            'text' => '/start abc123',
                        ],
                    ],
                ],
            ]),
            'api.telegram.org/*/sendMessage*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('telegram:fetch-updates')->assertSuccessful();

        $owner->refresh();
        $this->assertSame('999888', $owner->telegram_chat_id);
        $this->assertTrue($owner->notify_telegram);
        $this->assertNotNull($link->refresh()->used_at);
    }

    public function test_an_unknown_or_expired_token_does_not_link_anyone(): void
    {
        config(['services.telegram.bot_token' => 'fake-token']);

        Http::fake([
            'api.telegram.org/*/getUpdates*' => Http::response([
                'result' => [
                    [
                        'update_id' => 1,
                        'message' => [
                            'chat' => ['id' => 111222],
                            'text' => '/start does-not-exist',
                        ],
                    ],
                ],
            ]),
            'api.telegram.org/*/sendMessage*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('telegram:fetch-updates')->assertSuccessful();

        $this->assertDatabaseMissing('users', ['telegram_chat_id' => '111222']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage'));
    }

    public function test_an_already_used_token_cannot_be_replayed(): void
    {
        config(['services.telegram.bot_token' => 'fake-token']);
        [, $owner] = $this->makeTenantWithOwner('TenantB');

        TelegramLinkToken::create([
            'user_id' => $owner->id,
            'token' => 'used-token',
            'expires_at' => now()->addMinutes(30),
            'used_at' => now(),
        ]);

        Http::fake([
            'api.telegram.org/*/getUpdates*' => Http::response([
                'result' => [
                    [
                        'update_id' => 1,
                        'message' => [
                            'chat' => ['id' => 333444],
                            'text' => '/start used-token',
                        ],
                    ],
                ],
            ]),
            'api.telegram.org/*/sendMessage*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('telegram:fetch-updates')->assertSuccessful();

        $this->assertNull($owner->refresh()->telegram_chat_id);
    }
}
