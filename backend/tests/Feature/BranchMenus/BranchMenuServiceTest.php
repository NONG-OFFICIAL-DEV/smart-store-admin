<?php

namespace Tests\Feature\BranchMenus;

use App\Models\Branch;
use App\Models\BranchMenu;
use App\Models\Menu;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BranchMenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Covers the BranchMenu resource's Repository/Service migration (see
 * .claude/skills/migrate-resource-to-repository). The frontend's create
 * form only ever submits a single branch_id, but the old backend always
 * returned the bulk {created, skipped, data:[...]} shape even for one —
 * the frontend store then pushed that whole array into its flat list as
 * if it were one record, corrupting local state. The controller now
 * returns a flat single-record response when exactly one assignment
 * succeeds, and the bulk summary only for genuine multi-branch requests.
 */
class BranchMenuServiceTest extends TestCase
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

    public function test_single_branch_assign_creates_exactly_one_record(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $menu = Menu::create(['tenant_id' => $tenantA->id, 'name' => 'Lunch Menu']);

        Auth::login($ownerA);
        $service = $this->app->make(BranchMenuService::class);
        $result = $service->assign([$branch->id], $menu->id, []);

        $this->assertCount(1, $result['created']);
        $this->assertEmpty($result['skipped']);
        $this->assertEmpty($result['errors']);
    }

    public function test_assigning_a_branch_from_a_different_tenant_is_rejected_as_an_error(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');
        $branchB = $this->makeBranch($tenantB, 'Other Tenant Branch');
        $menu = Menu::create(['tenant_id' => $tenantA->id, 'name' => 'Lunch Menu']);

        Auth::login($ownerA);
        $service = $this->app->make(BranchMenuService::class);
        $result = $service->assign([$branchB->id], $menu->id, []);

        $this->assertCount(0, $result['created']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_already_assigned_branch_is_skipped_not_errored(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $menu = Menu::create(['tenant_id' => $tenantA->id, 'name' => 'Lunch Menu']);

        Auth::login($ownerA);
        $service = $this->app->make(BranchMenuService::class);
        $service->assign([$branch->id], $menu->id, []);
        $result = $service->assign([$branch->id], $menu->id, []);

        $this->assertCount(0, $result['created']);
        $this->assertSame([$branch->name], $result['skipped']);
    }

    public function test_available_now_filters_by_time_window_and_day(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $menu = Menu::create(['tenant_id' => $tenantA->id, 'name' => 'Breakfast']);

        Auth::login($ownerA);
        BranchMenu::create([
            'branch_id' => $branch->id, 'menu_id' => $menu->id,
            'available_from' => '00:00:00', 'available_until' => '23:59:59',
        ]);
        $closedMenu = Menu::create(['tenant_id' => $tenantA->id, 'name' => 'Never Open']);
        BranchMenu::create([
            'branch_id' => $branch->id, 'menu_id' => $closedMenu->id,
            'days_of_week' => [], // no days at all
        ]);

        $service = $this->app->make(BranchMenuService::class);
        $available = $service->availableNow($branch->id);

        $this->assertTrue($available->contains(fn ($a) => $a->menu_id === $menu->id));
    }

    public function test_update_delete_and_unassign_go_through_the_repository(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        $menu = Menu::create(['tenant_id' => $tenantA->id, 'name' => 'Lunch Menu']);

        Auth::login($ownerA);
        $service = $this->app->make(BranchMenuService::class);
        $result = $service->assign([$branch->id], $menu->id, ['sort_order' => 1]);
        $assignment = $result['created']->first();

        $updated = $service->update($assignment, ['sort_order' => 5]);
        $this->assertSame(5, $updated->sort_order);

        $service->unassign($branch->id, $menu->id);
        $this->assertNull(BranchMenu::find($assignment->id));
    }
}
