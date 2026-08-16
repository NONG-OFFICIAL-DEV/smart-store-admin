<?php

namespace Tests\Feature\Features;

use App\Models\BranchType;
use App\Models\Branch;
use App\Models\BusinessType;
use App\Models\Feature;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the runtime feature-gating layer built on top of the previously
 * dormant branch_type_features pivot (see FeatureSeeder/BranchTypeFeatureSeeder,
 * both now wired into DatabaseSeeder): Feature::codesForBranchType() /
 * codesForTenant() and BranchType::hasFeature(), which the EnsureBranchHasFeature
 * middleware and AuthController::me()'s `features` field both read from.
 */
class FeatureGateTest extends TestCase
{
    use RefreshDatabase;

    private function makeBranchTypeWithFeatures(array $featureCodes, ?BusinessType $businessType = null): BranchType
    {
        $businessType ??= BusinessType::create(['code' => 'TEST_BU', 'name' => 'Test Business']);
        $branchType = BranchType::create(['business_type_id' => $businessType->id, 'code' => 'TEST_BRANCH_TYPE_'.uniqid(), 'name' => 'Test Branch Type']);

        foreach ($featureCodes as $code) {
            $feature = Feature::firstOrCreate(['code' => $code], ['name' => $code]);
            $branchType->features()->attach($feature->id, ['is_required' => false, 'is_default' => true]);
        }

        return $branchType;
    }

    public function test_has_feature_is_true_only_for_features_actually_attached(): void
    {
        $branchType = $this->makeBranchTypeWithFeatures(['POS', 'MENU']);

        $this->assertTrue($branchType->hasFeature('POS'));
        $this->assertTrue($branchType->hasFeature('MENU'));
        $this->assertFalse($branchType->hasFeature('RESERVATION'));
    }

    public function test_codes_for_branch_type_is_cached_and_reflects_attached_features(): void
    {
        $branchType = $this->makeBranchTypeWithFeatures(['POS', 'KDS', 'TABLE_MGMT']);

        $codes = Feature::codesForBranchType($branchType->id);

        $this->assertEqualsCanonicalizing(['POS', 'KDS', 'TABLE_MGMT'], $codes);
    }

    public function test_codes_for_tenant_is_the_union_across_every_branch(): void
    {
        $businessType = BusinessType::create(['code' => 'TEST_BU2', 'name' => 'Test Business 2']);
        $hqType = $this->makeBranchTypeWithFeatures(['POS', 'MENU', 'RESERVATION'], $businessType);
        $popupType = $this->makeBranchTypeWithFeatures(['POS', 'MENU'], $businessType);

        $owner = User::create(['first_name' => 'O', 'last_name' => 'Owner', 'email' => 'featuretest-owner@example.test']);
        $tenant = Tenant::create(['name' => 'Feature Tenant', 'slug' => 'feature-tenant-'.uniqid(), 'owner_user_id' => $owner->id]);

        Branch::create(['tenant_id' => $tenant->id, 'branch_type_id' => $hqType->id, 'name' => 'HQ', 'address_line1' => '1 St', 'city' => 'PP']);
        Branch::create(['tenant_id' => $tenant->id, 'branch_type_id' => $popupType->id, 'name' => 'Popup', 'address_line1' => '2 St', 'city' => 'PP']);

        $codes = Feature::codesForTenant($tenant->id);

        // Union — RESERVATION only exists on one of the two branches, but
        // still shows up, since nav-level gating is "does ANY branch support this."
        $this->assertEqualsCanonicalizing(['POS', 'MENU', 'RESERVATION'], $codes);
    }

    public function test_codes_for_tenant_with_no_branches_is_empty(): void
    {
        $owner = User::create(['first_name' => 'O', 'last_name' => 'Owner2', 'email' => 'featuretest-owner2@example.test']);
        $tenant = Tenant::create(['name' => 'Empty Tenant', 'slug' => 'empty-tenant-'.uniqid(), 'owner_user_id' => $owner->id]);

        $this->assertSame([], Feature::codesForTenant($tenant->id));
    }
}
