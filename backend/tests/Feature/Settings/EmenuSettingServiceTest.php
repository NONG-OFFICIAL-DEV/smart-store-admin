<?php

namespace Tests\Feature\Settings;

use App\Models\EmenuSetting;
use App\Services\EmenuSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmenuSettingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolved_base_url_falls_back_to_env_config_when_nothing_is_stored(): void
    {
        config(['app.frontend_url' => 'https://env-fallback.test']);

        $this->assertSame('https://env-fallback.test', EmenuSetting::baseUrl());
    }

    public function test_db_stored_base_url_takes_priority_over_env_config(): void
    {
        config(['app.frontend_url' => 'https://env-fallback.test']);
        EmenuSetting::create(['base_url' => 'https://menu.example.test/']);

        $this->assertSame('https://menu.example.test', EmenuSetting::baseUrl());
    }

    public function test_updating_settings_stores_the_base_url(): void
    {
        $service = $this->app->make(EmenuSettingService::class);

        $result = $service->updateSettings(['base_url' => 'https://menu.example.test']);

        $this->assertSame('https://menu.example.test', $result['base_url']);
        $this->assertSame('https://menu.example.test', $result['resolved_base_url']);
    }

    public function test_blank_base_url_on_update_clears_the_override_back_to_env_fallback(): void
    {
        config(['app.frontend_url' => 'https://env-fallback.test']);
        $service = $this->app->make(EmenuSettingService::class);
        $service->updateSettings(['base_url' => 'https://menu.example.test']);

        $result = $service->updateSettings(['base_url' => null]);

        $this->assertNull($result['base_url']);
        $this->assertSame('https://env-fallback.test', $result['resolved_base_url']);
    }

    public function test_table_qr_code_uses_the_configured_emenu_base_url(): void
    {
        EmenuSetting::create(['base_url' => 'https://menu.example.test']);

        $owner = \App\Models\User::create([
            'email' => 'owner@example.test', 'first_name' => 'Owner', 'last_name' => 'User', 'is_super_admin' => false,
        ]);
        $tenant = \App\Models\Tenant::create([
            'name' => 'TestCo', 'slug' => 'testco', 'owner_user_id' => $owner->id,
        ]);
        $branch = \App\Models\Branch::create([
            'tenant_id' => $tenant->id, 'name' => 'Main',
        ]);
        $table = \App\Models\Table::create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'table_number' => 'T1',
        ]);

        $this->assertStringStartsWith("https://menu.example.test/menu/{$branch->slug}/table/", $table->qr_code);
    }
}
