<?php

namespace Tests\Feature\Loyalty;

use App\Models\Customer;
use App\Models\LoyaltyReward;
use App\Models\LoyaltySetting;
use App\Models\Tenant;
use App\Models\TelegramSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LoyaltyScheduledNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithOwner(string $name): array
    {
        $owner = User::create([
            'email' => strtolower($name).'@example.test',
            'first_name' => $name,
            'last_name' => 'Owner',
            'is_super_admin' => false,
        ]);

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => strtolower($name).'-'.substr((string) $owner->id, 0, 8),
            'owner_user_id' => $owner->id,
        ]);

        return [$tenant, $owner];
    }

    protected function setUp(): void
    {
        parent::setUp();
        TelegramSetting::current()->update(['bot_token' => 'test-bot-token', 'bot_username' => 'test_bot']);
    }

    public function test_notifies_a_customer_with_enough_points_exactly_n_days_before_a_reward_expires(): void
    {
        [$tenant] = $this->makeTenantWithOwner('TenantA');
        $reward = LoyaltyReward::create([
            'tenant_id' => $tenant->id, 'name' => 'Free Coffee', 'points_cost' => 10,
            'is_active' => true, 'valid_until' => now()->addDays(3)->toDateString(),
        ]);
        $eligible = Customer::create([
            'tenant_id' => $tenant->id, 'first_name' => 'Sok',
            'telegram_chat_id' => '111', 'notify_telegram' => true,
        ]);
        $eligible->addPoints(10, null, 'adjust');
        $tooFew = Customer::create([
            'tenant_id' => $tenant->id, 'first_name' => 'Dara',
            'telegram_chat_id' => '222', 'notify_telegram' => true,
        ]);

        Http::fake(['api.telegram.org/*/sendMessage*' => Http::response(['ok' => true])]);

        $this->artisan('loyalty:notify-rewards-expiring')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['chat_id'] === '111' && str_contains($request['text'], 'Free Coffee'));
    }

    public function test_does_not_notify_outside_the_exact_threshold_day(): void
    {
        [$tenant] = $this->makeTenantWithOwner('TenantA');
        LoyaltyReward::create([
            'tenant_id' => $tenant->id, 'name' => 'Free Coffee', 'points_cost' => 10,
            'is_active' => true, 'valid_until' => now()->addDays(5)->toDateString(),
        ]);
        $customer = Customer::create([
            'tenant_id' => $tenant->id, 'first_name' => 'Sok',
            'telegram_chat_id' => '111', 'notify_telegram' => true,
        ]);
        $customer->addPoints(10, null, 'adjust');

        Http::fake();

        $this->artisan('loyalty:notify-rewards-expiring')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_respects_the_tenants_notify_reward_expiring_toggle(): void
    {
        [$tenant] = $this->makeTenantWithOwner('TenantA');
        LoyaltySetting::forTenant($tenant->id)->update(['notify_reward_expiring' => false]);
        LoyaltyReward::create([
            'tenant_id' => $tenant->id, 'name' => 'Free Coffee', 'points_cost' => 10,
            'is_active' => true, 'valid_until' => now()->addDays(3)->toDateString(),
        ]);
        $customer = Customer::create([
            'tenant_id' => $tenant->id, 'first_name' => 'Sok',
            'telegram_chat_id' => '111', 'notify_telegram' => true,
        ]);
        $customer->addPoints(10, null, 'adjust');

        Http::fake();

        $this->artisan('loyalty:notify-rewards-expiring')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_notifies_a_customer_whose_birthday_is_today(): void
    {
        [$tenant] = $this->makeTenantWithOwner('TenantA');
        $birthdayCustomer = Customer::create([
            'tenant_id' => $tenant->id, 'first_name' => 'Sok',
            'telegram_chat_id' => '111', 'notify_telegram' => true,
            'date_of_birth' => now()->subYears(25)->toDateString(),
        ]);
        $notToday = Customer::create([
            'tenant_id' => $tenant->id, 'first_name' => 'Dara',
            'telegram_chat_id' => '222', 'notify_telegram' => true,
            'date_of_birth' => now()->subYears(25)->subDay()->toDateString(),
        ]);

        Http::fake(['api.telegram.org/*/sendMessage*' => Http::response(['ok' => true])]);

        $this->artisan('loyalty:notify-birthdays')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['chat_id'] === '111' && str_contains($request['text'], 'Sok'));
    }

    public function test_respects_the_tenants_notify_birthday_toggle(): void
    {
        [$tenant] = $this->makeTenantWithOwner('TenantA');
        LoyaltySetting::forTenant($tenant->id)->update(['notify_birthday' => false]);
        Customer::create([
            'tenant_id' => $tenant->id, 'first_name' => 'Sok',
            'telegram_chat_id' => '111', 'notify_telegram' => true,
            'date_of_birth' => now()->subYears(25)->toDateString(),
        ]);

        Http::fake();

        $this->artisan('loyalty:notify-birthdays')->assertSuccessful();

        Http::assertNothingSent();
    }
}
