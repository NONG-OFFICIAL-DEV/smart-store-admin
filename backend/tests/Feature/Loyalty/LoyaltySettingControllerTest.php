<?php

namespace Tests\Feature\Loyalty;

use App\Http\Controllers\Api\LoyaltySettingController;
use App\Models\LoyaltySetting;
use App\Models\TelegramSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LoyaltySettingControllerTest extends TestCase
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

    private function controller(): LoyaltySettingController
    {
        return $this->app->make(LoyaltySettingController::class);
    }

    public function test_show_returns_the_tenants_defaults_on_first_call(): void
    {
        [, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);

        $response = $this->controller()->show(new Request());
        $data = $response->getData(true)['data'];

        $this->assertTrue($data['enabled']);
        $this->assertSame('per_order', $data['earning_type']);
        $this->assertEquals(1.0, $data["earning_rate"]);
        $this->assertTrue($data['notify_stamps_earned']);
    }

    public function test_update_persists_the_earning_rule_and_notify_toggles(): void
    {
        [, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);

        $response = $this->controller()->update(Request::create('/loyalty-settings', 'PUT', [
            'earning_type' => 'per_currency',
            'earning_rate' => 2000,
            'notify_birthday' => false,
        ]));
        $data = $response->getData(true)['data'];

        $this->assertSame('per_currency', $data['earning_type']);
        $this->assertEquals(2000.0, $data["earning_rate"]);
        $this->assertFalse($data['notify_birthday']);

        // Re-fetching confirms it was actually persisted, not just echoed back.
        $again = $this->controller()->show(new Request())->getData(true)['data'];
        $this->assertSame('per_currency', $again['earning_type']);
    }

    public function test_settings_are_isolated_per_tenant(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerA);
        $this->controller()->update(Request::create('/loyalty-settings', 'PUT', ['earning_rate' => 5]));

        Auth::login($ownerB);
        $dataB = $this->controller()->show(new Request())->getData(true)['data'];

        $this->assertEquals(1.0, $dataB["earning_rate"]);
        $this->assertEquals(
            5.0,
            LoyaltySetting::withoutGlobalScopes()->where('tenant_id', $tenantA->id)->first()->earning_rate
        );
    }

    public function test_show_includes_a_join_url_once_a_bot_username_is_configured(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        TelegramSetting::create(['bot_username' => 'MyStoreBot']);
        Auth::login($owner);

        $data = $this->controller()->show(new Request())->getData(true)['data'];

        $this->assertSame("https://t.me/MyStoreBot?startapp={$tenant->slug}", $data['join_url']);
    }

    public function test_show_omits_the_join_url_when_no_bot_is_configured(): void
    {
        [, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);

        $data = $this->controller()->show(new Request())->getData(true)['data'];

        $this->assertNull($data['join_url']);
    }

    public function test_update_rejects_an_invalid_earning_type(): void
    {
        [, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->controller()->update(Request::create('/loyalty-settings', 'PUT', [
            'earning_type' => 'not-a-real-type',
        ]));
    }
}
