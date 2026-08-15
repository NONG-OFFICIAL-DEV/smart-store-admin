<?php

namespace Tests\Feature\FloorPlans;

use App\Models\Branch;
use App\Models\FloorPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FloorPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Covers the FloorPlan resource's Repository/Service migration (see
 * .claude/skills/migrate-resource-to-repository). store()/show()/update()/
 * destroy() were all empty stubs, and the byBranch route pointed at a
 * controller method that didn't exist — this resource had no working CRUD
 * at all despite full route registration.
 */
class FloorPlanServiceTest extends TestCase
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
        return Branch::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'address_line1' => '123 Main St',
            'city' => 'Phnom Penh',
        ]);
    }

    public function test_create_actually_persists_unlike_the_old_empty_stub(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');

        Auth::login($ownerA);
        $service = $this->app->make(FloorPlanService::class);
        $floorPlan = $service->create(['branch_id' => $branch->id, 'name' => 'Main Floor']);

        $this->assertNotNull(FloorPlan::find($floorPlan->id));
    }

    public function test_by_branch_only_returns_that_branchs_floor_plans(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch1 = $this->makeBranch($tenantA, 'Branch1');
        $branch2 = $this->makeBranch($tenantA, 'Branch2');

        Auth::login($ownerA);
        $service = $this->app->make(FloorPlanService::class);
        $service->create(['branch_id' => $branch1->id, 'name' => 'Ground Floor']);
        $service->create(['branch_id' => $branch2->id, 'name' => 'Ground Floor']);

        $this->assertSame(1, $service->byBranch($branch1, [])->total());
        $this->assertSame(1, $service->byBranch($branch2, [])->total());
    }

    public function test_a_tenant_owner_only_sees_their_own_floor_plans(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');
        $branchA = $this->makeBranch($tenantA, 'A-Main');
        $branchB = $this->makeBranch($tenantB, 'B-Main');

        Auth::login($ownerA);
        $service = $this->app->make(FloorPlanService::class);
        $service->create(['branch_id' => $branchA->id, 'name' => 'Main']);

        Auth::login($ownerB);
        $service->create(['branch_id' => $branchB->id, 'name' => 'Main']);

        Auth::login($ownerA);
        $this->assertSame(1, $service->list([])->total());
    }

    public function test_update_and_delete_go_through_the_repository(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');

        Auth::login($ownerA);
        $service = $this->app->make(FloorPlanService::class);
        $floorPlan = $service->create(['branch_id' => $branch->id, 'name' => 'Main Floor']);

        $updated = $service->update($floorPlan, ['name' => 'Ground Floor']);
        $this->assertSame('Ground Floor', $updated->name);

        $service->delete($floorPlan);
        $this->assertNull(FloorPlan::find($floorPlan->id));
    }
}
