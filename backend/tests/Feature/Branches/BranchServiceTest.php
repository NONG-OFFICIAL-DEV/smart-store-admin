<?php

namespace Tests\Feature\Branches;

use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Models\BusinessType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BranchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Covers the Branch resource's Repository/Service migration (see
 * .claude/skills/migrate-resource-to-repository). Two real bugs found
 * while migrating: (1) Branch::generateSlug()'s duplicate check ran under
 * TenantScope, so it only ever checked the current tenant's branches even
 * though `slug` is globally unique at the DB level — two tenants both
 * naming a branch "Downtown" would 500 on the DB constraint instead of
 * getting "-1" appended. (2) BranchResource must expose `branch_type`/
 * `business_type` (snake_case) — relation methods are camelCase
 * (branchType()/businessType()), and BranchDetail.vue reads
 * `tenant.business_type.code` unconditionally (no optional chaining),
 * which threw on every load under the old raw-Eloquent-serialization shape.
 */
class BranchServiceTest extends TestCase
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

    public function test_a_tenant_owner_only_sees_their_own_branches(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerA);
        $service = $this->app->make(BranchService::class);
        $service->create(['name' => 'A Branch', 'address_line1' => '1 St', 'city' => 'PP'], new Request());

        Auth::login($ownerB);
        $service->create(['name' => 'B Branch', 'address_line1' => '2 St', 'city' => 'PP'], new Request());

        Auth::login($ownerA);
        $this->assertSame(1, $service->list([])->total());

        Auth::login($ownerB);
        $this->assertSame(1, $service->list([])->total());
    }

    public function test_two_tenants_naming_a_branch_the_same_thing_both_get_a_working_unique_slug(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerA);
        $service = $this->app->make(BranchService::class);
        $branchA = $service->create(['name' => 'Downtown', 'address_line1' => '1 St', 'city' => 'PP'], new Request());

        Auth::login($ownerB);
        $branchB = $service->create(['name' => 'Downtown', 'address_line1' => '2 St', 'city' => 'PP'], new Request());

        $this->assertSame('downtown', $branchA->slug);
        $this->assertSame('downtown-1', $branchB->slug);
    }

    public function test_create_assigns_the_resolved_tenant_id_not_a_spoofed_one(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerA);
        $service = $this->app->make(BranchService::class);

        $branch = $service->create([
            'name' => 'Main', 'address_line1' => '1 St', 'city' => 'PP',
            'tenant_id' => $tenantB->id,
        ], new Request());

        $this->assertSame($tenantA->id, $branch->tenant_id);
    }

    public function test_detail_payload_includes_snake_case_business_type_matching_frontend_expectations(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $businessType = BusinessType::create(['code' => 'RESTAURANT', 'name' => 'Restaurant']);
        $tenantA->update(['business_type_id' => $businessType->id]);

        Auth::login($ownerA);
        $service = $this->app->make(BranchService::class);
        $branch = $service->create(['name' => 'Main', 'address_line1' => '1 St', 'city' => 'PP'], new Request());

        $detail = $service->detail($branch);
        $json = (new BranchResource($detail['branch']))->response()->getData(true);

        $this->assertSame('RESTAURANT', $json['data']['tenant']['business_type']['code']);
        $this->assertArrayHasKey('orders_today', $detail['stats']);
        $this->assertArrayHasKey('total', $detail['table_summary']);
    }

    public function test_update_toggle_and_delete_go_through_the_repository(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');

        Auth::login($ownerA);
        $service = $this->app->make(BranchService::class);
        $branch = $service->create(['name' => 'Main', 'address_line1' => '1 St', 'city' => 'PP', 'is_open' => true], new Request());

        $updated = $service->update($branch, ['name' => 'Main Renamed']);
        $this->assertSame('Main Renamed', $updated->name);

        $toggled = $service->toggleOpen($branch);
        $this->assertFalse($toggled->is_open);

        $service->delete($branch);
        $this->assertNull(Branch::find($branch->id));
    }
}
