<?php

namespace Tests\Feature\BusinessTypes;

use App\Models\BranchType;
use App\Models\BusinessType;
use App\Services\BusinessTypeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BusinessTypeController::update/destroy used a route param bound
 * implicitly to `BusinessType $businessType` (camelCase), but the
 * apiResource-registered route generated `{business_type}` (Laravel's
 * default snake_case for a hyphenated resource name) — a name mismatch
 * that would break implicit route-model-binding. Standardized on
 * `{business_type}` everywhere. This is a global platform catalog
 * (no #[ScopedBy] — correct, matches Plan).
 */
class BusinessTypeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_orders_by_sort_order(): void
    {
        BusinessType::create(['code' => 'mart', 'name' => 'Mart', 'sort_order' => 2]);
        BusinessType::create(['code' => 'coffee', 'name' => 'Coffee', 'sort_order' => 1]);

        $service = $this->app->make(BusinessTypeService::class);
        $results = $service->list();

        $this->assertSame('coffee', $results->first()->code);
        $this->assertSame('mart', $results->last()->code);
    }

    public function test_branch_types_for_only_returns_active_ones_ordered_hq_first(): void
    {
        $businessType = BusinessType::create(['code' => 'restaurant', 'name' => 'Restaurant']);

        BranchType::create(['business_type_id' => $businessType->id, 'code' => 'branch', 'name' => 'Branch', 'is_hq' => false, 'is_active' => true, 'sort_order' => 1]);
        BranchType::create(['business_type_id' => $businessType->id, 'code' => 'hq', 'name' => 'HQ', 'is_hq' => true, 'is_active' => true, 'sort_order' => 2]);
        BranchType::create(['business_type_id' => $businessType->id, 'code' => 'inactive', 'name' => 'Inactive', 'is_hq' => false, 'is_active' => false, 'sort_order' => 0]);

        $service = $this->app->make(BusinessTypeService::class);
        $results = $service->branchTypesFor($businessType->id);

        $this->assertCount(2, $results);
        $this->assertSame('HQ', $results->first()->name);
    }
}
