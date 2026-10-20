<?php

namespace Tests\Feature\Tables;

use App\Models\Branch;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers the Table resource's Repository/Service migration (see
 * .claude/skills/migrate-resource-to-repository). index()'s search
 * referenced a `name` column that doesn't exist on this table at all
 * (fields are table_number/status/etc.) — would 500 the instant `search`
 * was passed. `byBranch` and `updateStatus` routes pointed at controller
 * methods that didn't exist at all (guaranteed 500s), now fixed.
 */
class TableServiceTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_a_tenant_owner_only_sees_their_own_tables_and_by_branch_filters(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');
        $branchA1 = $this->makeBranch($tenantA, 'A-Main');
        $branchA2 = $this->makeBranch($tenantA, 'A-Second');
        $branchB = $this->makeBranch($tenantB, 'B-Main');

        Auth::login($ownerA);
        $service = $this->app->make(TableService::class);
        $service->create(['branch_id' => $branchA1->id, 'table_number' => 'T1']);
        $service->create(['branch_id' => $branchA2->id, 'table_number' => 'T1']);

        Auth::login($ownerB);
        $service->create(['branch_id' => $branchB->id, 'table_number' => 'T1']);

        Auth::login($ownerA);
        $this->assertSame(2, $service->list([])->total());
        $this->assertSame(1, $service->byBranch($branchA1, [])->total());

        Auth::login($ownerB);
        $this->assertSame(1, $service->list([])->total());
    }

    public function test_search_by_table_number_works_where_the_old_name_column_would_have_500d(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');

        Auth::login($ownerA);
        $service = $this->app->make(TableService::class);
        $service->create(['branch_id' => $branch->id, 'table_number' => 'A1']);
        $service->create(['branch_id' => $branch->id, 'table_number' => 'B2']);

        $result = $service->list(['search' => 'A1']);
        $this->assertSame(1, $result->total());
    }

    public function test_update_status_and_delete_go_through_the_repository(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');

        Auth::login($ownerA);
        $service = $this->app->make(TableService::class);
        $table = $service->create(['branch_id' => $branch->id, 'table_number' => 'A1']);

        $updated = $service->updateStatus($table, 'occupied');
        $this->assertSame('occupied', $updated->status);

        $service->delete($table);
        $this->assertNull(Table::find($table->id));
    }

    public function test_qr_code_is_auto_generated_on_create_and_regeneration_works(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');

        Auth::login($ownerA);
        $service = $this->app->make(TableService::class);
        $table = $service->create(['branch_id' => $branch->id, 'table_number' => 'A1']);

        $this->assertNotNull($table->fresh()->qr_image_path);

        $info = $service->qrInfo($table);
        $this->assertArrayHasKey('qr_image_url', $info);
        $this->assertStringContainsString($branch->slug, $info['url']);
    }

    public function test_duplicate_table_number_in_the_same_branch_fails_validation(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');

        Auth::login($ownerA);
        Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);

        $validator = \Illuminate\Support\Facades\Validator::make(
            ['table_number' => 'A1'],
            ['table_number' => \Illuminate\Validation\Rule::unique('tables', 'table_number')->where('branch_id', $branch->id)]
        );

        $this->assertTrue($validator->fails());
    }

    public function test_list_includes_is_occupied_and_open_session_summary(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        Auth::login($ownerA);

        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);
        $session = \App\Models\TableSession::create([
            'table_id' => $table->id,
            'branch_id' => $branch->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);
        \App\Models\Order::create([
            'branch_id' => $branch->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_type' => 'dine_in',
            'status' => 'pending',
            'source' => 'pos',
            'subtotal' => 5,
            'tax_amount' => 0,
            'service_charge_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 5,
            'queue_number' => 1,
        ]);

        $service = $this->app->make(TableService::class);
        $paginator = $service->list(['perPage' => -1]);
        $resource = new \App\Http\Resources\TableResource($paginator->getCollection()->firstWhere('id', $table->id));
        $data = $resource->toArray(request());

        $this->assertTrue($data['is_occupied']);
        $this->assertSame(1, $data['open_session']['order_count']);
        $this->assertEquals(5.0, $data['open_session']['total']);
    }

    public function test_list_marks_a_table_with_no_open_session_as_not_occupied(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        Auth::login($ownerA);

        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);

        $service = $this->app->make(TableService::class);
        $paginator = $service->list(['perPage' => -1]);
        $resource = new \App\Http\Resources\TableResource($paginator->getCollection()->firstWhere('id', $table->id));
        $data = $resource->toArray(request());

        $this->assertFalse($data['is_occupied']);
        $this->assertNull($data['open_session']);
    }

    public function test_reorder_assigns_sequential_sort_order_matching_the_given_id_order(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        Auth::login($ownerA);

        $a = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);
        $b = Table::create(['branch_id' => $branch->id, 'table_number' => 'A2']);
        $c = Table::create(['branch_id' => $branch->id, 'table_number' => 'A3']);

        $service = $this->app->make(TableService::class);
        $service->reorder([$c->id, $a->id, $b->id]);

        $this->assertSame(0, $c->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
        $this->assertSame(2, $b->fresh()->sort_order);
    }

    public function test_reorder_silently_skips_an_id_belonging_to_another_tenant(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');
        $service = $this->app->make(TableService::class);

        Auth::login($ownerA);
        $branchA = $this->makeBranch($tenantA, 'Main A');
        $a = Table::create(['branch_id' => $branchA->id, 'table_number' => 'A1']);

        Auth::login($ownerB);
        $branchB = $this->makeBranch($tenantB, 'Main B');
        $b = Table::create(['branch_id' => $branchB->id, 'table_number' => 'B1']);

        Auth::login($ownerA);
        $service->reorder([$a->id, $b->id]);

        $this->assertSame(0, $a->fresh()->sort_order);
        // $b belongs to TenantB — untouched by TenantA's reorder call.
        $this->assertSame(0, $b->fresh()->sort_order);
    }

    public function test_list_defaults_to_sort_order(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $branch = $this->makeBranch($tenantA, 'Main');
        Auth::login($ownerA);

        $z = Table::create(['branch_id' => $branch->id, 'table_number' => 'Z', 'sort_order' => 0]);
        $a = Table::create(['branch_id' => $branch->id, 'table_number' => 'A', 'sort_order' => 1]);

        $service = $this->app->make(TableService::class);
        $items = $service->list(['perPage' => -1])->getCollection()->values();

        $this->assertSame($z->id, $items[0]->id);
        $this->assertSame($a->id, $items[1]->id);
    }
}
