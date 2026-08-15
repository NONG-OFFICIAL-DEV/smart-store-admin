<?php

namespace Tests\Feature\Suppliers;

use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SupplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Covers the Supplier resource's Repository/Service migration (see
 * .claude/skills/migrate-resource-to-repository), same fixture style as
 * tests/Feature/Security/TenantScopeTest.php.
 */
class SupplierServiceTest extends TestCase
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

    private function makeSupplier(Tenant $tenant, string $name, bool $isActive = true): Supplier
    {
        return Supplier::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'is_active' => $isActive,
        ]);
    }

    public function test_a_tenant_owner_only_sees_their_own_suppliers(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        $this->makeSupplier($tenantA, 'Acme Foods');
        $this->makeSupplier($tenantA, 'Acme Drinks');
        $this->makeSupplier($tenantB, 'Bravo Supplies');

        Auth::login($ownerA);
        $this->assertSame(2, Supplier::count());
        $this->assertSame(3, Supplier::withoutGlobalScopes()->count());

        $service = $this->app->make(SupplierService::class);
        $this->assertSame(2, $service->list([])->total());

        Auth::login($ownerB);
        $this->assertSame(1, $service->list([])->total());
    }

    public function test_is_active_filter_is_applied(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $this->makeSupplier($tenantA, 'Active Co', true);
        $this->makeSupplier($tenantA, 'Inactive Co', false);

        Auth::login($ownerA);
        $service = $this->app->make(SupplierService::class);

        $this->assertSame(1, $service->list(['is_active' => '1'])->total());
        $this->assertSame(1, $service->list(['is_active' => '0'])->total());
        $this->assertSame(2, $service->list([])->total());
    }

    public function test_create_assigns_the_resolved_tenant_id_not_a_spoofed_one(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerA);
        $service = $this->app->make(SupplierService::class);

        $supplier = $service->create([
            'name' => 'Charlie Wholesale',
            'tenant_id' => $tenantB->id,
        ], new Request());

        $this->assertSame($tenantA->id, $supplier->tenant_id);
    }

    public function test_update_and_delete_go_through_the_repository(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $supplier = $this->makeSupplier($tenantA, 'Dana Distribution');

        Auth::login($ownerA);
        $service = $this->app->make(SupplierService::class);

        $updated = $service->update($supplier, ['name' => 'Dana Distribution Co']);
        $this->assertSame('Dana Distribution Co', $updated->name);

        $service->delete($supplier);
        $this->assertNull(Supplier::find($supplier->id));
    }
}
