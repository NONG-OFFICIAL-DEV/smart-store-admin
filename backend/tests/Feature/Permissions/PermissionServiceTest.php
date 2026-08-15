<?php

namespace Tests\Feature\Permissions;

use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OwnerRoleProvisioner;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the Permission resource's Repository/Service migration (see
 * .claude/skills/migrate-resource-to-repository). Permission is a global,
 * non-tenant-scoped catalog table (no #[ScopedBy] — see CLAUDE.md), so
 * there's no tenant-isolation case here; the one real behavior to verify
 * is the perPage=-1 "give me everything" convention the frontend relies on.
 */
class PermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A data migration (2026_07_28_000001_add_billing_manage_permission)
        // seeds a real 'billing.manage' row — the catalog is never actually
        // empty. Clear it so these tests can assert exact counts.
        Permission::query()->delete();
    }

    public function test_per_page_negative_one_returns_the_full_catalog_not_a_default_page(): void
    {
        $service = $this->app->make(PermissionService::class);

        for ($i = 0; $i < 20; $i++) {
            Permission::create(['code' => "perm.{$i}", 'group' => 'test']);
        }

        $result = $service->list(['perPage' => -1]);

        $this->assertSame(20, $result->total());
        $this->assertCount(20, $result->items());
    }

    public function test_default_pagination_is_capped_normally(): void
    {
        $service = $this->app->make(PermissionService::class);

        for ($i = 0; $i < 20; $i++) {
            Permission::create(['code' => "perm.{$i}", 'group' => 'test']);
        }

        $result = $service->list([]);

        $this->assertSame(20, $result->total());
        $this->assertSame(15, $result->perPage());
        $this->assertCount(15, $result->items());
    }

    public function test_create_update_and_delete_go_through_the_repository(): void
    {
        $service = $this->app->make(PermissionService::class);

        $permission = $service->create(['code' => 'customers.manage', 'group' => 'customers']);
        $this->assertSame('customers.manage', $permission->code);

        $updated = $service->update($permission, ['description' => 'Manage customers']);
        $this->assertSame('Manage customers', $updated->description);

        $service->delete($permission);
        $this->assertNull(Permission::find($permission->id));
    }

    public function test_creating_a_permission_auto_attaches_it_to_every_tenants_owner_role(): void
    {
        // This hook (Permission::boot()'s static::created()) was dropped by
        // a merge that resolved the conflict in favor of this migration's
        // version of Permission.php — restored here, since AuthController
        // ::me() also calls Permission::allCodesCached(), which was missing
        // entirely (a live 500 for super-admins/owners) until this fix.
        $owner = User::create(['email' => 'owner@example.test', 'first_name' => 'O', 'last_name' => 'Wner', 'is_super_admin' => false]);
        $tenant = Tenant::create(['name' => 'TenantA', 'slug' => 'tenant-a', 'owner_user_id' => $owner->id]);
        $ownerRole = app(OwnerRoleProvisioner::class)->ensureFor($tenant);

        $service = $this->app->make(PermissionService::class);
        $permission = $service->create(['code' => 'new.permission', 'group' => 'test']);

        $this->assertTrue($ownerRole->fresh()->permissions->contains('id', $permission->id));
    }
}
