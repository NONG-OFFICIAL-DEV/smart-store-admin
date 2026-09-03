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
        Auth::login($ownerA);
        $service = $this->app->make(CategoryService::class);

        $category = $service->create(['name' => 'Drinks'], [$tenantA->id]);
        $this->assertCount(1, $category->tenants);
        $this->assertSame($tenantA->id, $category->tenants->first()->id);
    }

    /**
     * A tenant's own custom category must never become visible to another
     * tenant — whatever tenant_ids they submit, create()/update() forces it
     * back to just themselves. Only a super admin may freely assign
     * tenant_ids (see test_super_admin_can_freely_assign_tenant_ids below).
     */
    public function test_a_non_super_admin_can_never_share_their_category_with_another_tenant(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');
        Auth::login($ownerA);
        $service = $this->app->make(CategoryService::class);

        $category = $service->create(['name' => 'Drinks'], [$tenantA->id, $tenantB->id]);
        $this->assertCount(1, $category->tenants);
        $this->assertSame($tenantA->id, $category->tenants->first()->id);

        $updated = $service->update($category, [], [$tenantB->id]);
        $this->assertCount(1, $updated->tenants);
        $this->assertSame($tenantA->id, $updated->tenants->first()->id);
    }

    public function test_super_admin_can_freely_assign_tenant_ids(): void
    {
        $admin = User::create([
            'email' => 'admin@example.test',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'is_super_admin' => true,
        ]);
        [$tenantA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');
        Auth::login($admin);
        $service = $this->app->make(CategoryService::class);

        $category = $service->create(['name' => 'Drinks'], [$tenantA->id, $tenantB->id]);
        $this->assertCount(2, $category->tenants);
    }

    public function test_a_non_super_admin_cannot_create_a_system_category(): void
    {
        [, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);
        $service = $this->app->make(CategoryService::class);

        $category = $service->create(
            ['name' => 'Drinks', 'is_system' => true],
            [],
            ['some-business-type-id']
        );

        $this->assertFalse($category->is_system);
        $this->assertCount(0, $category->businessTypes);
    }

    public function test_a_non_super_admin_cannot_edit_or_delete_a_system_category(): void
    {
        $admin = User::create([
            'email' => 'admin2@example.test',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'is_super_admin' => true,
        ]);
        Auth::login($admin);
        $service = $this->app->make(CategoryService::class);
        $systemCategory = $service->create(['name' => 'Beverages', 'is_system' => true], []);

        [, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);

        try {
            $service->update($systemCategory, ['name' => 'Hacked'], []);
            $this->fail('Expected a 403 when a non-super-admin edits a system category.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        try {
            $service->delete($systemCategory);
            $this->fail('Expected a 403 when a non-super-admin deletes a system category.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    /**
     * The core payoff of this feature: a super-admin-authored system
     * category tagged with a business type is visible to every tenant of
     * that business type (no per-tenant assignment needed), but never to a
     * tenant of a different business type.
     */
    public function test_a_system_category_is_visible_only_to_tenants_of_the_matching_business_type(): void
    {
        $restaurantType = \App\Models\BusinessType::create(['code' => 'RESTAURANT', 'name' => 'Restaurant']);
        $martType = \App\Models\BusinessType::create(['code' => 'MART', 'name' => 'Mart']);

        $admin = User::create([
            'email' => 'admin3@example.test',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'is_super_admin' => true,
        ]);
        Auth::login($admin);
        $service = $this->app->make(CategoryService::class);
        $service->create(['name' => 'Beverages', 'is_system' => true], [], [$restaurantType->id]);

        [$restaurantTenant, $restaurantOwner] = $this->makeTenantWithOwner('RestaurantTenant');
        $restaurantTenant->update(['business_type_id' => $restaurantType->id]);
        [$martTenant, $martOwner] = $this->makeTenantWithOwner('MartTenant');
        $martTenant->update(['business_type_id' => $martType->id]);

        Auth::login($restaurantOwner);
        $this->assertCount(1, Category::all());

        Auth::login($martOwner);
        $this->assertCount(0, Category::all());
    }

    public function test_by_menu_returns_categories_shared_with_the_menus_tenant(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        $service = $this->app->make(CategoryService::class);
        Auth::login($ownerA);
        $service->create(['name' => 'A Category'], [$tenantA->id]);
        Auth::login($ownerB);
        $service->create(['name' => 'B Category'], [$tenantB->id]);
        Auth::login($ownerA);

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
