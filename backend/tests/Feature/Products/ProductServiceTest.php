<?php

namespace Tests\Feature\Products;

use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Covers the ProductController/ProductControllerV2 merge: a single
 * ProductService::update() now has to safely serve both V2's full-form
 * replace (variants/units/image) and V1's narrow partial toggle
 * (ProductManagement.vue's {is_available} payload) without wiping
 * variants/units when the client didn't send those keys. Also covers the
 * cup_sizes/temperature_options/shelf_life_hours/supplier_code fields that
 * used to silently drop (no $fillable, no DB column) and the fixed
 * findByBarcode() stock check (was $product->stock, a nonexistent
 * attribute that always evaluated to "out of stock").
 */
class ProductServiceTest extends TestCase
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

    private function makeCategory(string $name = 'Drinks'): Category
    {
        return Category::create(['name' => $name]);
    }

    public function test_create_persists_variants_units_and_the_new_food_and_mart_fields(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $category = $this->makeCategory();
        $service = $this->app->make(ProductService::class);

        $product = $service->create([
            'category_id' => $category->id,
            'name' => 'Iced Latte',
            'base_price' => 4.5,
            'cup_sizes' => ['S', 'M', 'L'],
            'temperature_options' => ['hot', 'iced'],
            'shelf_life_hours' => 48,
            'supplier_code' => 'SUP-001',
            'variants' => [
                ['name' => 'Oat Milk', 'price_adjustment' => 0.5],
            ],
            'units' => [
                ['unit_name' => 'cup', 'qty_per_base' => 1, 'retail_price' => 4.5, 'is_base_unit' => true, 'is_active' => true],
            ],
        ], $tenant->id);

        $this->assertSame(['S', 'M', 'L'], $product->cup_sizes);
        $this->assertSame(['hot', 'iced'], $product->temperature_options);
        $this->assertSame(48, $product->shelf_life_hours);
        $this->assertSame('SUP-001', $product->supplier_code);
        $this->assertCount(1, $product->variants);
        $this->assertCount(1, $product->units);
        $this->assertSame('Oat Milk', $product->variants->first()->name);
        $this->assertSame('cup', $product->units->first()->unit_name);
    }

    public function test_partial_update_toggling_availability_does_not_wipe_variants_or_units(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $category = $this->makeCategory();
        $service = $this->app->make(ProductService::class);

        $product = $service->create([
            'category_id' => $category->id,
            'name' => 'Cappuccino',
            'variants' => [
                ['name' => 'Large', 'price_adjustment' => 1],
            ],
            'units' => [
                ['unit_name' => 'cup', 'qty_per_base' => 1, 'retail_price' => 3.5, 'is_base_unit' => true, 'is_active' => true],
            ],
        ], $tenant->id);

        // Mirrors ProductManagement.vue's availability toggle: PUT with only
        // {is_available} — no variants/units keys present at all.
        $updated = $service->update($product, ['is_available' => false], null, false);

        $this->assertFalse($updated->is_available);
        $this->assertCount(1, $updated->variants);
        $this->assertCount(1, $updated->units);
    }

    public function test_full_update_with_variants_key_present_replaces_existing_variants(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $category = $this->makeCategory();
        $service = $this->app->make(ProductService::class);

        $product = $service->create([
            'category_id' => $category->id,
            'name' => 'Cappuccino',
            'variants' => [
                ['name' => 'Large', 'price_adjustment' => 1],
            ],
        ], $tenant->id);

        $updated = $service->update($product, [
            'variants' => [
                ['name' => 'Small', 'price_adjustment' => 0],
                ['name' => 'Medium', 'price_adjustment' => 0.5],
            ],
        ], null, false);

        $this->assertCount(2, $updated->variants);
        $this->assertEqualsCanonicalizing(['Small', 'Medium'], $updated->variants->pluck('name')->all());
    }

    public function test_a_non_super_admin_cannot_move_a_product_to_another_tenant_on_update(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');
        Auth::login($ownerA);
        $category = $this->makeCategory();
        $service = $this->app->make(ProductService::class);

        $product = $service->create(['category_id' => $category->id, 'name' => 'Muffin'], $tenantA->id);

        $updated = $service->update($product, ['name' => 'Muffin v2'], $tenantB->id, false);

        $this->assertSame($tenantA->id, $updated->tenant_id);
    }

    public function test_byCategory_scopes_the_listing_to_that_category(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $categoryA = $this->makeCategory('Coffee');
        $categoryB = $this->makeCategory('Tea');
        $service = $this->app->make(ProductService::class);

        $service->create(['category_id' => $categoryA->id, 'name' => 'Espresso'], $tenant->id);
        $service->create(['category_id' => $categoryA->id, 'name' => 'Americano'], $tenant->id);
        $service->create(['category_id' => $categoryB->id, 'name' => 'Green Tea'], $tenant->id);

        $this->assertSame(2, $service->byCategory($categoryA->id, [])->total());
        $this->assertSame(1, $service->byCategory($categoryB->id, [])->total());
    }

    public function test_attachModifierGroups_links_without_detaching_existing_links(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $category = $this->makeCategory();
        $service = $this->app->make(ProductService::class);

        $product = $service->create(['category_id' => $category->id, 'name' => 'Sandwich'], $tenant->id);
        $groupA = ModifierGroup::create(['tenant_id' => $tenant->id, 'name' => 'Bread']);
        $groupB = ModifierGroup::create(['tenant_id' => $tenant->id, 'name' => 'Extras']);

        $service->attachModifierGroups($product, [$groupA->id]);
        $product = $service->attachModifierGroups($product, [$groupB->id]);

        $this->assertEqualsCanonicalizing(
            [$groupA->id, $groupB->id],
            $product->modifierGroups->pluck('id')->all()
        );
    }

    public function test_findByBarcode_reports_out_of_stock_correctly_instead_of_always_failing(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $category = $this->makeCategory();
        $service = $this->app->make(ProductService::class);

        $inStock = $service->create([
            'category_id' => $category->id,
            'name' => 'Chips',
            'barcode' => 'IN-STOCK-1',
            'stock_quantity' => 5,
        ], $tenant->id);

        $outOfStock = $service->create([
            'category_id' => $category->id,
            'name' => 'Soda',
            'barcode' => 'OUT-OF-STOCK-1',
            'stock_quantity' => 0,
        ], $tenant->id);

        $found = $service->findByBarcode('IN-STOCK-1');
        $this->assertSame($inStock->id, $found->id);

        $this->expectException(ValidationException::class);
        $service->findByBarcode('OUT-OF-STOCK-1');
    }

    public function test_a_tenant_owner_only_sees_their_own_products_in_the_listing(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');
        $service = $this->app->make(ProductService::class);

        Auth::login($ownerA);
        $category = $this->makeCategory();
        $service->create(['category_id' => $category->id, 'name' => 'A Product'], $tenantA->id);

        Auth::login($ownerB);
        $categoryB = $this->makeCategory('B Category');
        $service->create(['category_id' => $categoryB->id, 'name' => 'B Product'], $tenantB->id);

        Auth::login($ownerA);
        $this->assertSame(1, $service->list([])->total());
    }
}
