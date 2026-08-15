<?php

namespace Tests\Feature\Notifications;

use App\Models\Tenant;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
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

    public function test_defaults_are_system_and_email_on_telegram_off_and_unlinked(): void
    {
        [, $owner] = $this->makeTenantWithOwner('TenantA');
        $service = $this->app->make(NotificationService::class);

        // User::create()'s in-memory instance doesn't pick up the DB column
        // defaults it never explicitly set — refresh() to read them back,
        // matching how a real request's $request->user() is always a fresh
        // fetch, never the instance returned by the original create() call.
        $prefs = $service->getPreferences($owner->refresh());

        $this->assertTrue($prefs['notify_system']);
        $this->assertTrue($prefs['notify_email']);
        $this->assertFalse($prefs['notify_telegram']);
        $this->assertFalse($prefs['telegram_linked']);
    }

    public function test_email_preference_can_be_turned_off(): void
    {
        [, $owner] = $this->makeTenantWithOwner('TenantB');
        $service = $this->app->make(NotificationService::class);

        $service->updatePreferences($owner, ['notify_email' => false]);

        $this->assertFalse($owner->refresh()->notify_email);
    }

    public function test_system_preference_can_be_turned_off(): void
    {
        [, $owner] = $this->makeTenantWithOwner('TenantH');
        $service = $this->app->make(NotificationService::class);

        $service->updatePreferences($owner, ['notify_system' => false]);

        $this->assertFalse($owner->refresh()->notify_system);
    }

    public function test_enabling_telegram_before_linking_a_chat_is_silently_dropped(): void
    {
        [, $owner] = $this->makeTenantWithOwner('TenantC');
        $service = $this->app->make(NotificationService::class);

        $service->updatePreferences($owner, ['notify_telegram' => true]);

        $this->assertFalse($owner->refresh()->notify_telegram);
    }

    public function test_enabling_telegram_after_linking_a_chat_succeeds(): void
    {
        [, $owner] = $this->makeTenantWithOwner('TenantD');
        $owner->update(['telegram_chat_id' => '123456']);
        $service = $this->app->make(NotificationService::class);

        $service->updatePreferences($owner, ['notify_telegram' => true]);

        $this->assertTrue($owner->refresh()->notify_telegram);
    }

    public function test_generating_a_telegram_link_url_creates_a_pending_token(): void
    {
        [, $owner] = $this->makeTenantWithOwner('TenantE');
        config(['services.telegram.bot_username' => 'test_bot']);
        $service = $this->app->make(NotificationService::class);

        $url = $service->createTelegramLinkUrl($owner);

        $this->assertStringStartsWith('https://t.me/test_bot?start=', $url);
        $this->assertDatabaseHas('telegram_link_tokens', ['user_id' => $owner->id]);
    }

    public function test_generating_a_new_link_url_invalidates_the_previous_pending_one(): void
    {
        [, $owner] = $this->makeTenantWithOwner('TenantF');
        $service = $this->app->make(NotificationService::class);

        $firstUrl = $service->createTelegramLinkUrl($owner);
        $firstToken = str($firstUrl)->after('start=')->toString();

        $service->createTelegramLinkUrl($owner);

        $this->assertDatabaseMissing('telegram_link_tokens', ['token' => $firstToken]);
    }

    public function test_unlinking_telegram_clears_the_chat_id_and_disables_the_preference(): void
    {
        [, $owner] = $this->makeTenantWithOwner('TenantG');
        $owner->update(['telegram_chat_id' => '123456', 'notify_telegram' => true]);
        $service = $this->app->make(NotificationService::class);

        $service->unlinkTelegram($owner);

        $owner->refresh();
        $this->assertNull($owner->telegram_chat_id);
        $this->assertFalse($owner->notify_telegram);
    }
}
