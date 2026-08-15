<?php

namespace Tests\Feature\Security;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Regression test for a real bug: App\Models\Scopes\TenantScope was fully
 * implemented but never registered on any model (no addGlobalScope/ScopedBy
 * anywhere) — every query on every tenant-owned table returned all tenants'
 * data with zero filtering. Fixed via #[ScopedBy(TenantScope::class)] on
 * every model whose table has tenant_id/branch_id, or (Category) goes
 * through the category_tenant pivot.
 */
class TenantScopeTest extends TestCase
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

    private function makeProduct(Tenant $tenant, string $name): Product
    {
        // Category has no tenant_id column (see CLAUDE.md) — tenant linkage
        // for products themselves is direct (products.tenant_id); the
        // category_tenant pivot is a separate concern this test doesn't need.
        $category = Category::create(['name' => "{$name} category"]);

        return Product::create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => $name,
        ]);
    }

    public function test_a_tenant_owner_only_sees_their_own_products(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        $this->makeProduct($tenantA, 'A Product 1');
        $this->makeProduct($tenantA, 'A Product 2');
        $this->makeProduct($tenantB, 'B Product 1');

        Auth::login($ownerA);
        $this->assertSame(2, Product::count());
        $this->assertSame(3, Product::withoutGlobalScopes()->count());

        Auth::login($ownerB);
        $this->assertSame(1, Product::count());
    }

    public function test_a_super_admin_sees_every_tenants_products(): void
    {
        [$tenantA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');
        $this->makeProduct($tenantA, 'A Product');
        $this->makeProduct($tenantB, 'B Product');

        $admin = User::create([
            'email' => 'admin@example.test',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'is_super_admin' => true,
        ]);

        Auth::login($admin);
        $this->assertSame(2, Product::count());
    }

    public function test_system_roles_with_null_tenant_id_stay_visible_to_every_tenant(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        Role::create(['tenant_id' => null, 'name' => 'Owner', 'is_system' => true]);
        Role::create(['tenant_id' => $tenantA->id, 'name' => 'Custom A Role', 'is_system' => false]);
        Role::create(['tenant_id' => $tenantB->id, 'name' => 'Custom B Role', 'is_system' => false]);

        Auth::login($ownerA);
        $names = Role::pluck('name')->all();
        $this->assertContains('Owner', $names, 'system role must stay visible');
        $this->assertContains('Custom A Role', $names);
        $this->assertNotContains('Custom B Role', $names, 'must not see another tenant\'s custom role');
    }
}
