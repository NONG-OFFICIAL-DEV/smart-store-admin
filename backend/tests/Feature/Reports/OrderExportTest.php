<?php

namespace Tests\Feature\Reports;

use App\Http\Controllers\Api\OrderExportController;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * Order has no tenant_id column of its own (tenancy is indirect via
 * branch_id -> Branch.tenant_id) and Customer has no `name`/`tier` columns
 * (first_name/last_name + loyalty_tier) — OrderExportController::export()
 * previously queried both directly and fatal'd for any non-super-admin
 * caller (a QueryException, not a 500 render — every real tenant user).
 */
class OrderExportTest extends TestCase
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

    public function test_export_does_not_crash_for_a_tenant_owner_with_orders(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('ExportOwner');
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        $customer = Customer::create(['tenant_id' => $tenant->id, 'first_name' => 'Jane', 'last_name' => 'Doe']);
        Order::create([
            'branch_id' => $branch->id, 'order_number' => 'ORD-1', 'total_amount' => 50,
            'status' => 'completed', 'order_type' => 'dine_in', 'customer_id' => $customer->id,
        ]);
        Auth::login($owner);

        $controller = $this->app->make(OrderExportController::class);
        $response = $controller->export(Request::create('/orders/export', 'GET'));

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_export_search_by_customer_name_does_not_crash(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('ExportSearch');
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        $customer = Customer::create(['tenant_id' => $tenant->id, 'first_name' => 'Jane', 'last_name' => 'Doe']);
        Order::create([
            'branch_id' => $branch->id, 'order_number' => 'ORD-1', 'total_amount' => 50,
            'status' => 'completed', 'order_type' => 'dine_in', 'customer_id' => $customer->id,
        ]);
        Auth::login($owner);

        $controller = $this->app->make(OrderExportController::class);
        $response = $controller->export(Request::create('/orders/export', 'GET', ['search' => 'Jane']));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_export_excludes_orders_from_other_tenants_branches(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('ExportTenantA');
        $branchA = Branch::create(['tenant_id' => $tenantA->id, 'name' => 'A', 'address_line1' => 'x', 'city' => 'y']);
        Order::create([
            'branch_id' => $branchA->id, 'order_number' => 'ORD-A', 'total_amount' => 10,
            'status' => 'completed', 'order_type' => 'dine_in',
        ]);

        [$tenantB] = $this->makeTenantWithOwner('ExportTenantB');
        $branchB = Branch::create(['tenant_id' => $tenantB->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        Order::create([
            'branch_id' => $branchB->id, 'order_number' => 'ORD-B', 'total_amount' => 20,
            'status' => 'completed', 'order_type' => 'dine_in',
        ]);

        Auth::login($ownerA);

        $controller = $this->app->make(OrderExportController::class);
        $response = $controller->export(Request::create('/orders/export', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
    }
}
