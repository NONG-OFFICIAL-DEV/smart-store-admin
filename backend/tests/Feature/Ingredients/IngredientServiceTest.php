<?php

namespace Tests\Feature\Ingredients;

use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\InventoryStock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\IngredientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Covers the Ingredient resource's Repository/Service migration (see
 * .claude/skills/migrate-resource-to-repository), same fixture style as
 * tests/Feature/Security/TenantScopeTest.php.
 */
class IngredientServiceTest extends TestCase
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

    private function makeIngredient(Tenant $tenant, string $name, array $attrs = []): Ingredient
    {
        return Ingredient::create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'unit' => 'kg',
        ], $attrs));
    }

    public function test_a_tenant_owner_only_sees_their_own_ingredients(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        $this->makeIngredient($tenantA, 'Flour');
        $this->makeIngredient($tenantA, 'Sugar');
        $this->makeIngredient($tenantB, 'Salt');

        Auth::login($ownerA);
        $this->assertSame(2, Ingredient::count());

        $service = $this->app->make(IngredientService::class);
        $this->assertSame(2, $service->list([])->total());

        Auth::login($ownerB);
        $this->assertSame(1, $service->list([])->total());
    }

    public function test_category_and_is_active_filters_are_applied(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $this->makeIngredient($tenantA, 'Flour', ['category' => 'dry goods', 'is_active' => true]);
        $this->makeIngredient($tenantA, 'Milk', ['category' => 'dairy', 'is_active' => false]);

        Auth::login($ownerA);
        $service = $this->app->make(IngredientService::class);

        $this->assertSame(1, $service->list(['category' => 'dry goods'])->total());
        $this->assertSame(1, $service->list(['is_active' => '1'])->total());
        $this->assertSame(1, $service->list(['is_active' => '0'])->total());
    }

    public function test_low_stock_filter_finds_ingredients_at_or_below_reorder_point(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = Branch::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Main',
            'address_line1' => '123 Main St',
            'city' => 'Phnom Penh',
        ]);

        $low = $this->makeIngredient($tenantA, 'Flour', ['reorder_point' => 10]);
        $ok = $this->makeIngredient($tenantA, 'Sugar', ['reorder_point' => 10]);

        InventoryStock::create(['branch_id' => $branch->id, 'ingredient_id' => $low->id, 'quantity_on_hand' => 5]);
        InventoryStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ok->id, 'quantity_on_hand' => 50]);

        Auth::login($ownerA);
        $service = $this->app->make(IngredientService::class);

        $result = $service->list(['low_stock' => '1']);
        $this->assertSame(1, $result->total());
        $this->assertSame('Flour', $result->items()[0]->name);
    }

    public function test_create_assigns_the_resolved_tenant_id_not_a_spoofed_one(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerA);
        $service = $this->app->make(IngredientService::class);

        $ingredient = $service->create([
            'name' => 'Cheese',
            'unit' => 'kg',
            'tenant_id' => $tenantB->id,
        ], new Request());

        $this->assertSame($tenantA->id, $ingredient->tenant_id);
    }

    public function test_update_and_delete_go_through_the_repository(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $ingredient = $this->makeIngredient($tenantA, 'Butter');

        Auth::login($ownerA);
        $service = $this->app->make(IngredientService::class);

        $updated = $service->update($ingredient, ['name' => 'Salted Butter']);
        $this->assertSame('Salted Butter', $updated->name);

        $service->delete($ingredient);
        $this->assertNull(Ingredient::find($ingredient->id));
    }
}
