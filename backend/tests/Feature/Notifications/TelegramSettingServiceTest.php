<?php

namespace Tests\Feature\Notifications;

use App\Models\TelegramSetting;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramSettingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_are_empty_by_default(): void
    {
        $service = $this->app->make(TelegramService::class);

        $settings = $service->getSettings();

        $this->assertNull($settings['bot_username']);
        $this->assertFalse($settings['has_token']);
        $this->assertNull($settings['token_preview']);
    }

    public function test_updating_settings_stores_the_token_and_username(): void
    {
        $service = $this->app->make(TelegramService::class);

        $result = $service->updateSettings([
            'bot_token' => 'abc123456789',
            'bot_username' => 'my_bot',
        ]);

        $this->assertTrue($result['has_token']);
        $this->assertSame('my_bot', $result['bot_username']);
        $this->assertSame('••••6789', $result['token_preview']);
    }

    public function test_blank_token_on_update_leaves_the_existing_token_untouched(): void
    {
        $service = $this->app->make(TelegramService::class);
        $service->updateSettings(['bot_token' => 'original-token', 'bot_username' => 'old_name']);

        $result = $service->updateSettings(['bot_token' => '', 'bot_username' => 'new_name']);

        $this->assertTrue($result['has_token']);
        $this->assertSame('new_name', $result['bot_username']);
        $this->assertSame('original-token', TelegramSetting::current()->bot_token);
    }

    public function test_db_stored_token_takes_priority_over_env_config(): void
    {
        config(['services.telegram.bot_token' => 'env-token']);
        TelegramSetting::create(['bot_token' => 'db-token']);

        $this->assertSame('db-token', TelegramSetting::token());
    }

    public function test_falls_back_to_env_config_when_nothing_is_stored_in_the_db(): void
    {
        config(['services.telegram.bot_token' => 'env-token']);

        $this->assertSame('env-token', TelegramSetting::token());
    }

    public function test_connection_test_fails_cleanly_with_no_token_configured(): void
    {
        $service = $this->app->make(TelegramService::class);

        $result = $service->testConnection();

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['error']);
    }

    public function test_connection_test_reports_success_for_a_valid_token(): void
    {
        TelegramSetting::create(['bot_token' => 'good-token']);
        Http::fake([
            'api.telegram.org/*/getMe' => Http::response([
                'ok' => true,
                'result' => ['username' => 'my_bot'],
            ]),
        ]);

        $service = $this->app->make(TelegramService::class);
        $result = $service->testConnection();

        $this->assertTrue($result['ok']);
        $this->assertSame('my_bot', $result['bot_username']);
    }

    public function test_connection_test_reports_failure_for_an_invalid_token(): void
    {
        TelegramSetting::create(['bot_token' => 'bad-token']);
        Http::fake([
            'api.telegram.org/*/getMe' => Http::response([
                'ok' => false,
                'description' => 'Unauthorized',
            ], 401),
        ]);

        $service = $this->app->make(TelegramService::class);
        $result = $service->testConnection();

        $this->assertFalse($result['ok']);
        $this->assertSame('Unauthorized', $result['error']);
    }
}
