<?php

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Covers the Category resource's Repository/Service migration (see
 * .claude/skills/migrate-resource-to-repository). Category has no
 * tenant_id at all — sharing is via the category_tenant pivot — so there's
 * no tenant-isolation test here; the interesting behavior is the
 * sort_order re-balancing logic and the menu_id cleanup (CLAUDE.md
 * documented this as a silent no-op; verified empirically it's actually a
 * hard 500 — Category::$fillable had menu_id and a menu() belongsTo, but
 * the column was never added to the categories table at all). menu_id
 * removed; menus/{menu}/categories, previously a dead route, now resolves
 * through the menu's tenant via category_tenant instead.
 */
class CategoryServiceTest extends TestCase
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

    public function test_setting_menu_id_no_longer_exists_as_a_fillable_field(): void
    {
        // Previously this attribute was silently dropped from $fillable's
        // perspective in theory but actually crashed on save with a real
        // Postgres "column does not exist" error whenever menu_id was
        // included in mass-assigned data.
        $this->assertNotContains('menu_id', (new Category())->getFillable());
    }

    public function test_create_without_sort_order_appends_to_the_end_of_its_parent(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);
        $service = $this->app->make(CategoryService::class);

        $first = $service->create(['name' => 'Drinks'], [$tenantA->id]);
        $second = $service->create(['name' => 'Food'], [$tenantA->id]);

        $this->assertSame(1, $first->sort_order);
        $this->assertSame(2, $second->sort_order);
    }

    public function test_inserting_at_a_specific_position_shifts_later_siblings_down(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);
        $service = $this->app->make(CategoryService::class);

        $a = $service->create(['name' => 'A', 'sort_order' => 0], [$tenantA->id]);
        $b = $service->create(['name' => 'B', 'sort_order' => 1], [$tenantA->id]);

        // Insert C at position 1 — B should shift to 2.
        $c = $service->create(['name' => 'C', 'sort_order' => 1], [$tenantA->id]);

        $this->assertSame(0, $a->fresh()->sort_order);
        $this->assertSame(1, $c->fresh()->sort_order);
        $this->assertSame(2, $b->fresh()->sort_order);
    }

    public function test_moving_a_category_later_shifts_the_ones_in_between_back(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);
        $service = $this->app->make(CategoryService::class);

        $a = $service->create(['name' => 'A', 'sort_order' => 0], [$tenantA->id]);
        $b = $service->create(['name' => 'B', 'sort_order' => 1], [$tenantA->id]);
        $c = $service->create(['name' => 'C', 'sort_order' => 2], [$tenantA->id]);

        // Move A from 0 to 2 — B and C should each shift back by one:
        // [A=0,B=1,C=2] -> [B=0,C=1,A=2].
        $service->update($a, ['sort_order' => 2], [$tenantA->id]);

        $this->assertSame(0, $b->fresh()->sort_order);
        $this->assertSame(1, $c->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
    }

    public function test_tenants_are_synced_on_create_and_update(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');
        Auth::login($ownerA);
        $service = $this->app->make(CategoryService::class);

        $category = $service->create(['name' => 'Drinks'], [$tenantA->id]);
        $this->assertCount(1, $category->tenants);

        $updated = $service->update($category, [], [$tenantA->id, $tenantB->id]);
        $this->assertCount(2, $updated->tenants);
    }

    public function test_by_menu_returns_categories_shared_with_the_menus_tenant(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');
        Auth::login($ownerA);

        $service = $this->app->make(CategoryService::class);
        $service->create(['name' => 'A Category'], [$tenantA->id]);
        $service->create(['name' => 'B Category'], [$tenantB->id]);

        $menu = Menu::create(['tenant_id' => $tenantA->id, 'name' => 'Lunch']);

        $result = $service->byMenu($menu);
        $this->assertCount(1, $result);
        $this->assertSame('A Category', $result->first()->name);
    }

    public function test_delete_goes_through_the_repository(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);
        $service = $this->app->make(CategoryService::class);
        $category = $service->create(['name' => 'Drinks'], []);

        $service->delete($category);
        $this->assertNull(Category::find($category->id));
    }
}
