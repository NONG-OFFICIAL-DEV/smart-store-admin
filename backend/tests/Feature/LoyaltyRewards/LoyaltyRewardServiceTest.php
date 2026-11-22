<?php

namespace Tests\Feature\LoyaltyRewards;

use App\Models\Branch;
use App\Models\BranchType;
use App\Models\BusinessType;
use App\Models\LoyaltyReward;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LoyaltyRewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LoyaltyRewardServiceTest extends TestCase
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

    private function makeBranch(Tenant $tenant, string $name): Branch
    {
        $businessType = BusinessType::firstOrCreate(['code' => 'restaurant'], ['name' => 'Restaurant']);
        $branchType = BranchType::firstOrCreate(
            ['business_type_id' => $businessType->id, 'code' => 'hq'],
            ['name' => 'Main Store', 'is_hq' => true]
        );

        return Branch::create([
            'tenant_id' => $tenant->id,
            'branch_type_id' => $branchType->id,
            'name' => $name,
        ]);
    }

    public function test_a_tenant_owner_only_sees_their_own_rewards(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');

        LoyaltyReward::create(['tenant_id' => $tenantA->id, 'name' => 'Free Coffee', 'points_cost' => 10]);
        LoyaltyReward::create(['tenant_id' => $tenantA->id, 'name' => 'Free Cake', 'points_cost' => 20]);
        LoyaltyReward::create(['tenant_id' => $tenantB->id, 'name' => 'Free Tea', 'points_cost' => 5]);

        Auth::login($ownerA);

        $service = $this->app->make(LoyaltyRewardService::class);
        $this->assertSame(2, $service->list([])->total());
    }

    public function test_create_assigns_the_resolved_tenant_id_not_a_spoofed_one(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerA);

        $service = $this->app->make(LoyaltyRewardService::class);
        $reward = $service->create([
            'name' => 'Free Matcha',
            'points_cost' => 10,
            'tenant_id' => $tenantB->id,
        ], new Request());

        $this->assertSame($tenantA->id, $reward->tenant_id);
    }

    public function test_update_and_delete_go_through_the_repository(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $reward = LoyaltyReward::create(['tenant_id' => $tenantA->id, 'name' => 'Free Coffee', 'points_cost' => 10]);

        Auth::login($ownerA);
        $service = $this->app->make(LoyaltyRewardService::class);

        $updated = $service->update($reward, ['points_cost' => 15]);
        $this->assertSame(15, $updated->points_cost);

        $service->delete($reward);
        $this->assertNull(LoyaltyReward::find($reward->id));
    }

    public function test_available_for_includes_tenant_wide_and_branch_specific_active_rewards_only(): void
    {
        [$tenantA] = $this->makeTenantWithOwner('TenantA');
        $branchA = $this->makeBranch($tenantA, 'Branch A');
        $branchB = $this->makeBranch($tenantA, 'Branch B');

        LoyaltyReward::create(['tenant_id' => $tenantA->id, 'name' => 'All Branches', 'points_cost' => 5]);

        $rewardA = LoyaltyReward::create(['tenant_id' => $tenantA->id, 'name' => 'Branch A Only', 'points_cost' => 8]);
        $rewardA->branches()->attach($branchA->id);

        $rewardB = LoyaltyReward::create(['tenant_id' => $tenantA->id, 'name' => 'Branch B Only', 'points_cost' => 8]);
        $rewardB->branches()->attach($branchB->id);

        LoyaltyReward::create(['tenant_id' => $tenantA->id, 'name' => 'Inactive', 'points_cost' => 1, 'is_active' => false]);

        $service = $this->app->make(LoyaltyRewardService::class);
        $names = $service->availableFor($tenantA->id, $branchA->id)->pluck('name')->all();

        $this->assertEqualsCanonicalizing(['All Branches', 'Branch A Only'], $names);
    }
}
