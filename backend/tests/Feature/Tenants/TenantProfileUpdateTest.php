<?php

namespace Tests\Feature\Tenants;

use App\Http\Controllers\Api\TenantController;
use App\Http\Requests\UpdateTenantProfileRequest;
use App\Models\BusinessType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Settings > Company Info tab — self-service update, deliberately a much
 * narrower field allowlist than the super-admin edit form (TenantService::update()).
 */
class TenantProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_only_the_safe_branding_fields(): void
    {
        $businessType = BusinessType::create(['name' => 'Restaurant', 'code' => 'restaurant', 'category' => 'food']);

        $owner = User::create([
            'email' => 'owner@example.test',
            'first_name' => 'Owner',
            'last_name' => 'User',
            'is_super_admin' => false,
        ]);

        $tenant = Tenant::create([
            'name' => 'Original Name',
            'slug' => 'original-slug',
            'owner_user_id' => $owner->id,
            'business_type_id' => $businessType->id,
            'is_active' => true,
            'currency' => 'USD',
        ]);

        $service = $this->app->make(TenantService::class);

        $updated = $service->updateProfile($tenant, [
            'name' => 'New Company Name',
            'logo_url' => 'https://example.test/logo.png',
            'primary_color' => '#123456',
            'currency' => 'KHR',
        ]);

        $this->assertSame('New Company Name', $updated->name);
        $this->assertSame('https://example.test/logo.png', $updated->logo_url);
        $this->assertSame('#123456', $updated->primary_color);
        $this->assertSame('KHR', $updated->currency);

        // Fields not in the allowlist must be untouched — these stay
        // admin-only (TenantService::update()).
        $this->assertSame('original-slug', $updated->slug);
        $this->assertSame($businessType->id, $updated->business_type_id);
        $this->assertTrue($updated->is_active);
    }

    public function test_pos_settings_can_be_narrowed_and_merges_with_existing_defaults(): void
    {
        $owner = User::create([
            'email' => 'owner2@example.test',
            'first_name' => 'Owner',
            'last_name' => 'User',
            'is_super_admin' => false,
        ]);

        $tenant = Tenant::create([
            'name' => 'Takeaway Coffee',
            'slug' => 'takeaway-coffee',
            'owner_user_id' => $owner->id,
            'currency' => 'USD',
        ]);

        // DB-level column default — every order type + customer + notes
        // enabled. (Tenant::create()'s own in-memory instance never syncs a
        // DB-computed default, hence the ->fresh() read here.)
        $this->assertEquals(Tenant::DEFAULT_POS_SETTINGS, $tenant->fresh()->pos_settings);

        $service = $this->app->make(TenantService::class);

        $updated = $service->updateProfile($tenant, [
            'name' => $tenant->name,
            'pos_settings' => [
                'order_types' => ['takeaway'],
                'customer_selection' => false,
                'order_notes' => false,
            ],
        ]);

        $this->assertSame(['takeaway'], $updated->pos_settings['order_types']);
        $this->assertFalse($updated->pos_settings['customer_selection']);
        $this->assertFalse($updated->pos_settings['order_notes']);
    }

    /**
     * The reported bug: the Company Info form only had a raw logo_url text
     * field, so any non-URL input (or a picked image file) 422'd with
     * "must be a valid URL". Fixed by adding real file-upload support,
     * mirroring TenantController::store()'s existing hasFile('logo') logic.
     * Exercised through the actual controller + FormRequest (not the
     * Service directly) since that's where the new file-handling lives.
     */
    public function test_uploading_a_logo_file_overrides_logo_url_and_is_stored(): void
    {
        Storage::fake('public');

        $owner = User::create([
            'email' => 'owner4@example.test',
            'first_name' => 'Owner',
            'last_name' => 'User',
            'is_super_admin' => false,
        ]);

        $tenant = Tenant::create([
            'name' => 'Corner Cafe',
            'slug' => 'corner-cafe',
            'owner_user_id' => $owner->id,
            'currency' => 'USD',
        ]);

        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $request = UpdateTenantProfileRequest::create(
            "/v1/tenants/{$tenant->id}/profile",
            'PUT',
            ['name' => $tenant->name],
            [],
            ['logo' => $file]
        );
        $request->setContainer($this->app);
        $request->setUserResolver(fn () => $owner);
        $request->validateResolved();

        $controller = $this->app->make(TenantController::class);
        $response = $controller->updateProfile($request, $tenant);
        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success']);
        $this->assertStringContainsString('/storage/logos/', $payload['data']['logo_url']);

        $storedPath = 'logos/'.basename($payload['data']['logo_url']);
        Storage::disk('public')->assertExists($storedPath);
    }

    public function test_pos_settings_are_left_untouched_when_omitted(): void
    {
        $owner = User::create([
            'email' => 'owner3@example.test',
            'first_name' => 'Owner',
            'last_name' => 'User',
            'is_super_admin' => false,
        ]);

        $tenant = Tenant::create([
            'name' => 'Full Service Restaurant',
            'slug' => 'full-service-restaurant',
            'owner_user_id' => $owner->id,
            'currency' => 'USD',
        ]);

        $service = $this->app->make(TenantService::class);

        $updated = $service->updateProfile($tenant, [
            'name' => 'Renamed Restaurant',
        ]);

        $this->assertEquals(Tenant::DEFAULT_POS_SETTINGS, $updated->pos_settings);
    }
}
