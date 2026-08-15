<?php

namespace Tests\Feature\Menus;

use App\Models\Branch;
use App\Models\Menu;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Covers the Menu resource's Repository/Service migration (see
 * .claude/skills/migrate-resource-to-repository). Real bug found: the
 * "only one default menu per tenant" check ran BEFORE $tenantId was
 * injected into $data, so it always matched where('tenant_id', null) —
 * zero rows. Setting a new default menu never actually unset the previous
 * one. Also: menus/{menu}/branches/sync routed to a controller method
 * that didn't exist at all.
 */
class MenuServiceTest extends TestCase
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

    public function test_setting_a_new_default_menu_actually_unsets_the_previous_one(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');

        Auth::login($ownerA);
        $service = $this->app->make(MenuService::class);
        $first = $service->create(['name' => 'Lunch', 'is_default' => true], new Request());
        $this->assertTrue($first->fresh()->is_default);

        $second = $service->create(['name' => 'Dinner', 'is_default' => true], new Request());

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_setting_default_on_update_also_unsets_others(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');

        Auth::login($ownerA);
        $service = $this->app->make(MenuService::class);
        $first = $service->create(['name' => 'Lunch', 'is_default' => true], new Request());
        $second = $service->create(['name' => 'Dinner'], new Request());

        $service->update($second, ['is_default' => true]);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_create_assigns_the_resolved_tenant_id_not_a_spoofed_one(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerA);
        $service = $this->app->make(MenuService::class);
        $menu = $service->create(['name' => 'Lunch', 'tenant_id' => $tenantB->id], new Request());

        $this->assertSame($tenantA->id, $menu->tenant_id);
    }

    public function test_sync_branches_actually_persists_unlike_the_old_dead_route(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch1 = $this->makeBranch($tenantA, 'Branch1');
        $branch2 = $this->makeBranch($tenantA, 'Branch2');

        Auth::login($ownerA);
        $service = $this->app->make(MenuService::class);
        $menu = $service->create(['name' => 'Lunch'], new Request());

        $service->syncBranches($menu, [$branch1->id, $branch2->id]);
        $this->assertCount(2, $menu->branches()->get());

        $service->syncBranches($menu, [$branch1->id]);
        $this->assertCount(1, $menu->branches()->get());
    }

    public function test_a_tenant_owner_only_sees_their_own_menus(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerA);
        $service = $this->app->make(MenuService::class);
        $service->create(['name' => 'A Menu'], new Request());

        Auth::login($ownerB);
        $service->create(['name' => 'B Menu'], new Request());

        Auth::login($ownerA);
        $this->assertSame(1, $service->list([])->total());
    }

    public function test_update_and_delete_go_through_the_repository(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');

        Auth::login($ownerA);
        $service = $this->app->make(MenuService::class);
        $menu = $service->create(['name' => 'Lunch'], new Request());

        $updated = $service->update($menu, ['name' => 'Brunch']);
        $this->assertSame('Brunch', $updated->name);

        $service->delete($menu);
        $this->assertNull(Menu::find($menu->id));
    }
}
