<?php

namespace Tests\Feature\ModifierOptions;

use App\Http\Controllers\Api\ModifierOptionController;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ModifierOptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Covers the ModifierOption resource's Repository/Service migration (see
 * .claude/skills/migrate-resource-to-repository). This is the important
 * one: modifier_options had NO #[ScopedBy(TenantScope::class)] at all —
 * not an unhandled column shape like other tables, genuinely no scope
 * registered. It's tenant-owned only indirectly via group_id ->
 * modifier_groups.tenant_id, and reachable via nested (but independently
 * id-bound) routes, so any authenticated same-tenant-permission user
 * could view/edit/delete ANY other tenant's modifier option directly by
 * id. Fixed by registering #[ScopedBy] and teaching TenantScope a new
 * 'modifier_options' case (same pattern as the existing 'categories' one).
 */
class ModifierOptionServiceTest extends TestCase
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

    public function test_a_tenant_cannot_see_another_tenants_modifier_option_via_direct_lookup(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerB);
        $groupB = ModifierGroup::create(['tenant_id' => $tenantB->id, 'name' => 'Size']);
        $optionB = ModifierOption::create(['group_id' => $groupB->id, 'name' => 'Large']);

        Auth::login($ownerA);
        // Direct find-by-id, exactly what route-model binding does for
        // GET/PUT/DELETE .../options/{option} — must not resolve.
        $this->assertNull(ModifierOption::find($optionB->id));
        $this->assertNotNull(ModifierOption::withoutGlobalScopes()->find($optionB->id));
    }

    public function test_a_tenant_owner_only_sees_options_in_their_own_groups(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerA);
        $groupA = ModifierGroup::create(['tenant_id' => $tenantA->id, 'name' => 'Size']);
        $service = $this->app->make(ModifierOptionService::class);
        $service->create($groupA, ['name' => 'Small']);
        $service->create($groupA, ['name' => 'Large']);

        Auth::login($ownerB);
        $groupB = ModifierGroup::create(['tenant_id' => $tenantB->id, 'name' => 'Toppings']);
        $service->create($groupB, ['name' => 'Cheese']);

        Auth::login($ownerA);
        $this->assertSame(2, $service->byGroup($groupA, [])->total());

        Auth::login($ownerB);
        $this->assertSame(1, $service->byGroup($groupB, [])->total());
    }

    public function test_referencing_an_option_from_a_different_group_in_the_same_tenant_is_rejected(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);

        $groupA = ModifierGroup::create(['tenant_id' => $tenantA->id, 'name' => 'Size']);
        $groupB = ModifierGroup::create(['tenant_id' => $tenantA->id, 'name' => 'Toppings']);
        $optionInGroupB = ModifierOption::create(['group_id' => $groupB->id, 'name' => 'Cheese']);

        $controller = $this->app->make(ModifierOptionController::class);
        // URL says groupA, but the option actually belongs to groupB.
        $response = $controller->show($groupA, $optionInGroupB);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_create_update_and_delete_go_through_the_repository(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);
        $group = ModifierGroup::create(['tenant_id' => $tenantA->id, 'name' => 'Size']);
        $service = $this->app->make(ModifierOptionService::class);

        $option = $service->create($group, ['name' => 'Small', 'price_adjustment' => 0]);
        $this->assertSame($group->id, $option->group_id);

        $updated = $service->update($option, ['price_adjustment' => 1.50]);
        $this->assertEquals(1.50, $updated->price_adjustment);

        $service->delete($option);
        $this->assertNull(ModifierOption::find($option->id));
    }
}
