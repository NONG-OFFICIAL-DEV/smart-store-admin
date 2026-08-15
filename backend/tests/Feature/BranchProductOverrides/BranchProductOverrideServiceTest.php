<?php

namespace Tests\Feature\BranchProductOverrides;

use App\Models\Branch;
use App\Models\BranchProductOverride;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BranchProductOverrideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Covers the BranchProductOverride resource's Repository/Service migration
 * (see .claude/skills/migrate-resource-to-repository). store()/show()/
 * update()/destroy() were all empty controller stubs (`//`) — this
 * resource had no working CRUD at all despite full route registration,
 * and index()'s search referenced a `note` column that doesn't exist on
 * this table (would 500 the instant `search` was passed).
 */
class BranchProductOverrideServiceTest extends TestCase
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

    private function makeProduct(Tenant $tenant, string $name): Product
    {
        $category = Category::create(['name' => "{$name} category"]);

        return Product::create(['tenant_id' => $tenant->id, 'category_id' => $category->id, 'name' => $name]);
    }

    public function test_upsert_creates_actually_persists_unlike_the_old_empty_stub(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $product = $this->makeProduct($tenantA, 'Burger');

        Auth::login($ownerA);
        $service = $this->app->make(BranchProductOverrideService::class);
        $override = $service->upsert(['branch_id' => $branch->id, 'product_id' => $product->id, 'override_price' => 5.99]);

        $this->assertNotNull(BranchProductOverride::find($override->id));
        $this->assertEquals(5.99, $override->override_price);
    }

    public function test_upsert_updates_the_existing_row_for_the_same_branch_and_product(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $product = $this->makeProduct($tenantA, 'Burger');

        Auth::login($ownerA);
        $service = $this->app->make(BranchProductOverrideService::class);
        $first = $service->upsert(['branch_id' => $branch->id, 'product_id' => $product->id, 'override_price' => 5.99]);
        $second = $service->upsert(['branch_id' => $branch->id, 'product_id' => $product->id, 'override_price' => 6.99]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, BranchProductOverride::count());
        $this->assertEquals(6.99, $second->override_price);
    }

    public function test_a_tenant_owner_only_sees_their_own_overrides(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');
        $branchA = $this->makeBranch($tenantA, 'A-Main');
        $branchB = $this->makeBranch($tenantB, 'B-Main');
        $productA = $this->makeProduct($tenantA, 'A-Item');
        $productB = $this->makeProduct($tenantB, 'B-Item');

        Auth::login($ownerA);
        $service = $this->app->make(BranchProductOverrideService::class);
        $service->upsert(['branch_id' => $branchA->id, 'product_id' => $productA->id]);

        Auth::login($ownerB);
        $service->upsert(['branch_id' => $branchB->id, 'product_id' => $productB->id]);

        Auth::login($ownerA);
        $this->assertSame(1, $service->list([])->total());

        Auth::login($ownerB);
        $this->assertSame(1, $service->list([])->total());
    }

    public function test_update_and_delete_go_through_the_repository(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $product = $this->makeProduct($tenantA, 'Burger');

        Auth::login($ownerA);
        $service = $this->app->make(BranchProductOverrideService::class);
        $override = $service->upsert(['branch_id' => $branch->id, 'product_id' => $product->id]);

        $updated = $service->update($override, ['is_available' => false]);
        $this->assertFalse($updated->is_available);

        $service->delete($override);
        $this->assertNull(BranchProductOverride::find($override->id));
    }
}
