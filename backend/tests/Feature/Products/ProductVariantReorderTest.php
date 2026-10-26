<?php

namespace Tests\Feature\Products;

use App\Http\Controllers\Api\ProductVariantController;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * ProductVariant is a deliberately-not-yet-migrated resource (plain
 * controller + BaseModel::store(), no Repository/Service) — see CLAUDE.md.
 * Covers the new drag-reorder endpoint only, called directly on the
 * controller (no Service to resolve), same as every other test here calls
 * through to app logic without a real HTTP round trip.
 */
class ProductVariantReorderTest extends TestCase
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

    public function test_reorder_rewrites_sort_order_to_match_the_given_sequence(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);

        $category = Category::create(['name' => 'Drinks']);
        $product = Product::create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Iced Tea',
            'base_price' => 2.5,
        ]);

        $small = ProductVariant::create(['product_id' => $product->id, 'name' => 'S', 'sort_order' => 0]);
        $medium = ProductVariant::create(['product_id' => $product->id, 'name' => 'M', 'sort_order' => 1]);
        $large = ProductVariant::create(['product_id' => $product->id, 'name' => 'L', 'sort_order' => 2]);

        $controller = $this->app->make(ProductVariantController::class);
        $request = Request::create('', 'POST', [
            'variant_ids' => [$large->id, $small->id, $medium->id],
        ]);

        $controller->reorder($request, $product);

        $this->assertSame(0, $large->fresh()->sort_order);
        $this->assertSame(1, $small->fresh()->sort_order);
        $this->assertSame(2, $medium->fresh()->sort_order);
    }

    public function test_reorder_ignores_a_variant_id_belonging_to_a_different_product(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);

        $category = Category::create(['name' => 'Drinks']);
        $productA = Product::create([
            'tenant_id' => $tenant->id, 'category_id' => $category->id,
            'name' => 'Iced Tea', 'base_price' => 2.5,
        ]);
        $productB = Product::create([
            'tenant_id' => $tenant->id, 'category_id' => $category->id,
            'name' => 'Coffee', 'base_price' => 3.0,
        ]);

        $ownVariant = ProductVariant::create(['product_id' => $productA->id, 'name' => 'S', 'sort_order' => 0]);
        $otherVariant = ProductVariant::create(['product_id' => $productB->id, 'name' => 'S', 'sort_order' => 0]);

        $controller = $this->app->make(ProductVariantController::class);
        $request = Request::create('', 'POST', [
            'variant_ids' => [$otherVariant->id, $ownVariant->id],
        ]);

        $controller->reorder($request, $productA);

        // Only productA's own variant moved; the other product's variant
        // (even though its id was submitted) is untouched.
        $this->assertSame(0, $otherVariant->fresh()->sort_order);
        $this->assertSame(1, $ownVariant->fresh()->sort_order);
    }
}
