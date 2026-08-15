<?php

namespace Tests\Feature\ModifierGroups;

use App\Http\Controllers\Api\ModifierGroupController;
use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ModifierGroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Covers the ModifierGroup resource's Repository/Service migration (see
 * .claude/skills/migrate-resource-to-repository). The products/{product}/
 * modifier-groups route pointed at a controller method (byProduct) that
 * didn't exist at all.
 */
class ModifierGroupServiceTest extends TestCase
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

    public function test_a_tenant_owner_only_sees_their_own_modifier_groups(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerA);
        $service = $this->app->make(ModifierGroupService::class);
        $service->create(['name' => 'Size'], new Request());

        Auth::login($ownerB);
        $service->create(['name' => 'Toppings'], new Request());

        Auth::login($ownerA);
        $this->assertSame(1, $service->list([])->total());
    }

    public function test_create_assigns_the_resolved_tenant_id_not_a_spoofed_one(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerA);
        $service = $this->app->make(ModifierGroupService::class);
        $group = $service->create(['name' => 'Size', 'tenant_id' => $tenantB->id], new Request());

        $this->assertSame($tenantA->id, $group->tenant_id);
    }

    public function test_update_and_delete_go_through_the_repository(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);
        $service = $this->app->make(ModifierGroupService::class);
        $group = $service->create(['name' => 'Size'], new Request());

        $updated = $service->update($group, ['is_required' => true]);
        $this->assertTrue($updated->is_required);

        $service->delete($group);
        $this->assertNull(ModifierGroup::find($group->id));
    }

    public function test_by_product_returns_this_products_modifier_groups(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);

        $category = Category::create(['name' => 'Drinks']);
        $product = Product::create(['tenant_id' => $tenantA->id, 'category_id' => $category->id, 'name' => 'Latte']);
        $group = ModifierGroup::create(['tenant_id' => $tenantA->id, 'name' => 'Size']);
        $product->modifierGroups()->sync([$group->id]);

        $controller = $this->app->make(ModifierGroupController::class);
        $response = $controller->byProduct($product);

        $data = $response->getData(true)['data'];
        $this->assertCount(1, $data);
        $this->assertSame($group->id, $data[0]['id']);
    }
}
