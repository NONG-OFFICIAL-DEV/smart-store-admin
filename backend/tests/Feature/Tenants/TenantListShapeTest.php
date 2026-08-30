<?php

namespace Tests\Feature\Tenants;

use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TenantController::index() previously returned raw Eloquent models with
 * every eager-loaded relation dumped in full (owner_pin_code, timezone,
 * every TenantSubscription column, etc.) — a real over-fetch for a list
 * view that only ever renders a handful of fields (see TenantView.vue).
 * TenantResource trims that down without touching the richer
 * TenantService::detail() shape used by the tenant detail page.
 */
class TenantListShapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_resource_only_exposes_the_fields_the_table_actually_uses(): void
    {
        $owner = User::create([
            'email' => 'owner@example.test', 'first_name' => 'Own', 'last_name' => 'Er',
        ]);
        Tenant::create([
            'name' => 'Test', 'slug' => 'test', 'owner_user_id' => $owner->id,
        ]);

        $paginator = $this->app->make(TenantService::class)->list([]);
        $shaped = json_decode(TenantResource::collection($paginator->items())->toJson(), true);

        $this->assertNotEmpty($shaped);
        $row = $shaped[0];

        foreach (['id', 'name', 'slug', 'logo_url', 'primary_color', 'currency', 'is_active', 'owner', 'business_type'] as $key) {
            $this->assertArrayHasKey($key, $row);
        }

        $this->assertArrayHasKey('first_name', $row['owner']);
        $this->assertArrayNotHasKey('owner_pin_code', $row);
        $this->assertArrayNotHasKey('timezone', $row);
        $this->assertArrayNotHasKey('plan_expires_at', $row);
    }
}
