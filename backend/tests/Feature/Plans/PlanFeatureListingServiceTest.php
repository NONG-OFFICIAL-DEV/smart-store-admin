<?php

namespace Tests\Feature\Plans;

use App\Models\PlanFeatureListing;
use App\Services\PlanFeatureListingService;
use App\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanFeatureListingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_updates_and_soft_deletes_a_listing(): void
    {
        $service = $this->app->make(PlanFeatureListingService::class);

        $listing = $service->create([
            'key' => 'inventory',
            'label_en' => 'Inventory management',
            'label_km' => null,
            'value_type' => 'boolean',
        ]);

        $this->assertSame('inventory', $listing->key);

        $updated = $service->update($listing, ['label_en' => 'Inventory']);
        $this->assertSame('Inventory', $updated->label_en);

        $service->delete($updated);

        $this->assertNull(PlanFeatureListing::find($listing->id));
        $this->assertNotNull(PlanFeatureListing::withTrashed()->find($listing->id));
    }

    public function test_resolve_for_plan_joins_catalog_with_plan_values_and_defaults(): void
    {
        $listingService = $this->app->make(PlanFeatureListingService::class);
        $planService = $this->app->make(PlanService::class);

        $listingService->create(['key' => 'inventory', 'label_en' => 'Inventory', 'value_type' => 'boolean', 'sort_order' => 0]);
        $listingService->create(['key' => 'products_limit', 'label_en' => 'Products', 'value_type' => 'text', 'sort_order' => 1]);

        $plan = $planService->create([
            'name' => 'Pro', 'code' => 'RESOLVE', 'price_usd' => 10, 'seats' => 1, 'storage_gb' => 1,
            'billing_cycles' => [['label' => 'Monthly', 'months' => 1, 'discount_percent' => 0]],
            'features' => [
                ['key' => 'inventory', 'value' => true],
            ],
        ]);

        $resolved = $listingService->resolveForPlan($plan);

        $this->assertCount(2, $resolved);
        $inventory = collect($resolved)->firstWhere('key', 'inventory');
        $this->assertTrue($inventory['value']);

        // No value saved yet for products_limit — defaults per its type.
        $productsLimit = collect($resolved)->firstWhere('key', 'products_limit');
        $this->assertSame(['en' => '', 'km' => null], $productsLimit['value']);
    }

    public function test_deleting_a_listing_referenced_by_a_plan_does_not_error(): void
    {
        $listingService = $this->app->make(PlanFeatureListingService::class);
        $planService = $this->app->make(PlanService::class);

        $listing = $listingService->create(['key' => 'inventory', 'label_en' => 'Inventory', 'value_type' => 'boolean']);

        $plan = $planService->create([
            'name' => 'Pro', 'code' => 'DELREF', 'price_usd' => 10, 'seats' => 1, 'storage_gb' => 1,
            'billing_cycles' => [['label' => 'Monthly', 'months' => 1, 'discount_percent' => 0]],
            'features' => [['key' => 'inventory', 'value' => true]],
        ]);

        $listingService->delete($listing);

        // Deactivated/deleted key just stops surfacing — resolveForPlan()
        // only iterates the active catalog, so the plan's raw feature row
        // is untouched but no longer appears in the resolved list.
        $resolved = $listingService->resolveForPlan($plan);
        $this->assertEmpty($resolved);
        $this->assertCount(1, $plan->features()->get());
    }
}
