<?php

namespace Tests\Feature\ActivityLogs;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * ActivityLog::log() resolved tenant_id/branch_id via $user->tenant_id /
 * $user->branch_id — attributes that don't exist on User at all (tenant
 * linkage lives on Staff/ownedTenant). Every row this ever wrote — for
 * EVERY model observed by ActivityLogObserver (Product, Category, Branch,
 * Tenant, Staff, Menu, BranchMenu, Table, Order, OrderItem, Payment,
 * Supplier, ProductVariant, Shift) plus every direct call site
 * (AuthController logins, PasswordService resets) — got tenant_id = NULL.
 * TenantScope treats a NULL tenant_id as "shared across every tenant" (by
 * design, for nullable system-role rows) — so every tenant's activity log
 * was silently visible to every other tenant with `reports.view`. This is
 * the regression test for the fix; treat any failure here as a real
 * regression, same seriousness as Security/TenantScopeTest.
 */
class ActivityLogServiceTest extends TestCase
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

    private function makeStaff(Tenant $tenant, Branch $branch, string $name): Staff
    {
        $user = User::create([
            'email' => strtolower($name).'@example.test',
            'first_name' => $name,
            'last_name' => 'Staffer',
        ]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => $name.' Role']);

        return Staff::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_logging_as_a_tenant_owner_resolves_the_real_tenant_id_not_null(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);

        ActivityLog::log(action: 'branch.created', description: 'Owner action');

        // Fixtures (Tenant::create, observed by ActivityLogObserver) log
        // their own entries pre-login with a null tenant_id — query by the
        // distinguishing description, not "latest", since created_at has
        // only second-level precision and both rows can tie.
        $log = ActivityLog::withoutGlobalScopes()->where('description', 'Owner action')->first();
        $this->assertSame($tenant->id, $log->tenant_id);
        $this->assertNull($log->branch_id);
    }

    public function test_logging_as_staff_resolves_both_tenant_id_and_branch_id(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantB');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant, 'Branch B');
        $staff = $this->makeStaff($tenant, $branch, 'Cashier');

        Auth::login($staff->user);
        ActivityLog::log(action: 'order.created', description: 'Staff action');

        $log = ActivityLog::withoutGlobalScopes()->where('description', 'Staff action')->first();
        $this->assertSame($tenant->id, $log->tenant_id);
        $this->assertSame($branch->id, $log->branch_id);
    }

    public function test_a_tenant_owner_only_sees_their_own_activity_logs(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantC');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantD');

        Auth::login($ownerA);
        ActivityLog::log(action: 'branch.created', description: 'A did something');

        Auth::login($ownerB);
        ActivityLog::log(action: 'branch.created', description: 'B did something');

        Auth::login($ownerA);
        $service = $this->app->make(ActivityLogService::class);
        $results = $service->list([]);

        $this->assertSame(1, $results->total());
        $this->assertSame('A did something', $results->first()->description);
    }

    public function test_filtering_by_action_works_against_the_real_column(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantE');
        Auth::login($owner);

        ActivityLog::log(action: 'product.created');
        ActivityLog::log(action: 'product.deleted');

        $service = $this->app->make(ActivityLogService::class);
        $results = $service->list(['action' => 'product.deleted']);

        $this->assertSame(1, $results->total());
        $this->assertSame('product.deleted', $results->first()->action);
    }

    public function test_search_matches_description_and_action(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantF');
        Auth::login($owner);

        ActivityLog::log(action: 'order.created', description: 'Order #123 placed');
        ActivityLog::log(action: 'staff.updated', description: 'Promoted to manager');

        $service = $this->app->make(ActivityLogService::class);
        $results = $service->list(['search' => 'Order #123']);

        $this->assertSame(1, $results->total());
    }

    public function test_date_range_filters_narrow_results(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantG');
        Auth::login($owner);

        ActivityLog::log(action: 'branch.created');

        $service = $this->app->make(ActivityLogService::class);

        $this->assertSame(1, $service->list([
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ])->total());

        $this->assertSame(0, $service->list([
            'date_from' => now()->addDays(2)->toDateString(),
        ])->total());
    }
}
