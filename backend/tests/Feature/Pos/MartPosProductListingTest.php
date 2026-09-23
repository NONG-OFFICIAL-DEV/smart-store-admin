<?php

namespace Tests\Feature\Pos;

use App\Http\Controllers\Api\MartPosController;
use App\Models\Branch;
use App\Models\BranchProductOverride;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * mart/pos/products and mart/pos/categories previously scoped by tenant_id
 * only — branch_id was resolved to a tenant and then thrown away, so every
 * branch of a multi-branch mart tenant saw the identical catalog at the
 * identical prices. branch_product_overrides already existed (and was
 * already used at checkout via Product::getPriceForBranch()) but was never
 * applied to the product/category LISTING the cashier actually browses.
 */
class MartPosProductListingTest extends TestCase
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

    private function makeProduct(string $tenantId, string $categoryName, float $basePrice): Product
    {
        // Category tenant-linkage goes through the category_tenant pivot,
        // not a tenant_id column — TenantScope's category special-case (see
        // CLAUDE.md) hides an unlinked category from every tenant-scoped
        // query, at every branch, which would otherwise look identical to
        // a real branch-override exclusion.
        $category = Category::create(['name' => $categoryName]);
        $category->tenants()->attach($tenantId);

        return $this->app->make(ProductService::class)->create([
            'category_id' => $category->id,
            'name' => 'Rice 5kg',
            'base_price' => $basePrice,
            'stock_quantity' => 100,
        ], $tenantId);
    }

    public function test_a_product_hidden_at_this_branch_is_excluded_from_the_listing(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branchA = $this->makeBranch($tenant, 'Branch A');
        $branchB = $this->makeBranch($tenant, 'Branch B');
        $product = $this->makeProduct($tenant->id, 'Groceries', 8.0);

        BranchProductOverride::create([
            'branch_id' => $branchA->id,
            'product_id' => $product->id,
            'is_available' => false,
        ]);

        $controller = $this->app->make(MartPosController::class);

        $responseA = $controller->products(Request::create('/mart/pos/products', 'GET', ['branch_id' => $branchA->id]));
        $idsA = collect($responseA->getData(true)['data']['data'])->pluck('id');
        $this->assertNotContains($product->id, $idsA);

        $responseB = $controller->products(Request::create('/mart/pos/products', 'GET', ['branch_id' => $branchB->id]));
        $idsB = collect($responseB->getData(true)['data']['data'])->pluck('id');
        $this->assertContains($product->id, $idsB);
    }

    public function test_a_branch_price_override_is_returned_as_selling_price(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantB');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant, 'Branch A');
        $product = $this->makeProduct($tenant->id, 'Groceries', 8.0);

        BranchProductOverride::create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'override_price' => 6.5,
        ]);

        $controller = $this->app->make(MartPosController::class);
        $response = $controller->products(Request::create('/mart/pos/products', 'GET', ['branch_id' => $branch->id]));
        $item = collect($response->getData(true)['data']['data'])->firstWhere('id', $product->id);

        $this->assertEquals(6.5, $item['selling_price']);
        $this->assertArrayNotHasKey('branchOverrides', $item);
    }

    public function test_a_product_with_no_override_is_unaffected(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantC');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant, 'Branch A');
        $product = $this->makeProduct($tenant->id, 'Groceries', 8.0);

        $controller = $this->app->make(MartPosController::class);
        $response = $controller->products(Request::create('/mart/pos/products', 'GET', ['branch_id' => $branch->id]));
        $item = collect($response->getData(true)['data']['data'])->firstWhere('id', $product->id);

        // selling_price is a real product column (used as an optional
        // override elsewhere) — the branch-override logic must not touch it
        // when there's no override for this branch, not force it to null.
        $this->assertNull($item['selling_price']);
        $this->assertEquals(8.0, $item['base_price']);
    }

    public function test_a_category_whose_only_product_is_hidden_at_this_branch_is_excluded(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantD');
        Auth::login($owner);
        $branchA = $this->makeBranch($tenant, 'Branch A');
        $branchB = $this->makeBranch($tenant, 'Branch B');
        $product = $this->makeProduct($tenant->id, 'Snacks', 2.0);

        BranchProductOverride::create([
            'branch_id' => $branchA->id,
            'product_id' => $product->id,
            'is_available' => false,
        ]);

        $controller = $this->app->make(MartPosController::class);

        $responseA = $controller->categories(Request::create('/mart/pos/categories', 'GET', ['branch_id' => $branchA->id]));
        $namesA = collect($responseA->getData(true)['data'])->pluck('name');
        $this->assertNotContains('Snacks', $namesA);

        $responseB = $controller->categories(Request::create('/mart/pos/categories', 'GET', ['branch_id' => $branchB->id]));
        $namesB = collect($responseB->getData(true)['data'])->pluck('name');
        $this->assertContains('Snacks', $namesB);
    }
}
