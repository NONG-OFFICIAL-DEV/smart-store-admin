<?php

namespace Tests\Feature\Reports;

use App\Http\Controllers\Api\MartPosController;
use App\Http\Controllers\Api\MartProductPerformanceController;
use App\Http\Controllers\Api\MartPurchaseReportController;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * A tenant owner has no Staff record at all, so mart POS/report endpoints
 * that read `auth()->user()->staff->branch_id` directly fatal for them.
 * ResolvesBranchContext fixes this: single-branch owners resolve
 * automatically, multi-branch owners with no explicit branch_id get a
 * clear 422 instead of a crash or silently-wrong branch data.
 */
class ResolvesBranchContextTest extends TestCase
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

    private function makeStaff(Tenant $tenant, Branch $branch, string $name): Staff
    {
        $user = User::create([
            'email' => strtolower($name).'@example.test',
            'first_name' => $name,
            'last_name' => 'Staff',
            'is_super_admin' => false,
        ]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Cashier']);

        return Staff::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'employee_code' => Staff::generateEmployeeCode($tenant->id),
            'is_active' => true,
        ]);
    }

    public function test_owner_with_a_single_branch_resolves_it_automatically_for_report_stock(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('SingleBranch');
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        Auth::login($owner);

        $controller = $this->app->make(MartPosController::class);
        $response = $controller->reportStock(Request::create('/mart/reports/inventory', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_owner_with_multiple_branches_and_no_branch_id_is_rejected_for_report_stock(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('MultiBranch');
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'A', 'address_line1' => 'x', 'city' => 'y']);
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        Auth::login($owner);

        $controller = $this->app->make(MartPosController::class);

        $this->expectException(ValidationException::class);
        $controller->reportStock(Request::create('/mart/reports/inventory', 'GET'));
    }

    public function test_owner_with_multiple_branches_can_pass_an_explicit_branch_id_for_report_stock(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('MultiBranchExplicit');
        $branchA = Branch::create(['tenant_id' => $tenant->id, 'name' => 'A', 'address_line1' => 'x', 'city' => 'y']);
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        Auth::login($owner);

        $controller = $this->app->make(MartPosController::class);
        $response = $controller->reportStock(Request::create('/mart/reports/inventory', 'GET', ['branch_id' => $branchA->id]));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_staff_without_an_explicit_branch_id_uses_their_own_assigned_branch_for_report_stock(): void
    {
        [$tenant] = $this->makeTenantWithOwner('StaffBranch');
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        $staff = $this->makeStaff($tenant, $branch, 'Cashier');
        Auth::login($staff->user);

        $controller = $this->app->make(MartPosController::class);
        $response = $controller->reportStock(Request::create('/mart/reports/inventory', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_owner_with_a_single_branch_resolves_it_automatically_for_product_performance(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('SingleBranchPerf');
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        Auth::login($owner);

        $controller = $this->app->make(MartProductPerformanceController::class);
        $response = $controller->index(Request::create('/mart/reports/product-performance', 'GET', [
            'date_from' => '2026-01-01',
            'date_to' => '2026-12-31',
        ]));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_owner_with_multiple_branches_and_no_branch_id_is_rejected_for_product_performance(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('MultiBranchPerf');
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'A', 'address_line1' => 'x', 'city' => 'y']);
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        Auth::login($owner);

        $controller = $this->app->make(MartProductPerformanceController::class);

        $this->expectException(ValidationException::class);
        $controller->index(Request::create('/mart/reports/product-performance', 'GET', [
            'date_from' => '2026-01-01',
            'date_to' => '2026-12-31',
        ]));
    }

    public function test_owner_with_a_single_branch_resolves_it_automatically_for_purchase_report(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('SingleBranchPurchases');
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        Auth::login($owner);

        $controller = $this->app->make(MartPurchaseReportController::class);
        $response = $controller->index(Request::create('/mart/reports/purchases', 'GET', [
            'date_from' => '2026-01-01',
            'date_to' => '2026-12-31',
        ]));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_owner_with_multiple_branches_and_no_branch_id_is_rejected_for_purchase_report(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('MultiBranchPurchases');
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'A', 'address_line1' => 'x', 'city' => 'y']);
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        Auth::login($owner);

        $controller = $this->app->make(MartPurchaseReportController::class);

        $this->expectException(ValidationException::class);
        $controller->index(Request::create('/mart/reports/purchases', 'GET', [
            'date_from' => '2026-01-01',
            'date_to' => '2026-12-31',
        ]));
    }
}
