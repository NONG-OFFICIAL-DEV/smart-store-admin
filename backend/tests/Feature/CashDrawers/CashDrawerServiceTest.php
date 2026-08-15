<?php

namespace Tests\Feature\CashDrawers;

use App\Models\Branch;
use App\Models\CashDrawer;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CashDrawerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * CashDrawerController::store/show/update/destroy were empty `//` stubs,
 * and the routes referencing `open`/`close` pointed at controller methods
 * that didn't exist at all (guaranteed 500) — the real business logic
 * lived only in App\Models\CashDrawer::open()/close(), never wired to a
 * route. Moved into CashDrawerService, now actually reachable. Also fixed:
 * index()'s search filtered a nonexistent `status` column.
 */
class CashDrawerServiceTest extends TestCase
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
            'last_name' => 'Cashier',
        ]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => $name.' Role']);

        return Staff::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_open_creates_a_session_with_the_given_opening_float(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant, 'Branch A');
        $staff = $this->makeStaff($tenant, $branch, 'Cashier');

        $service = $this->app->make(CashDrawerService::class);
        $drawer = $service->open(['branch_id' => $branch->id, 'staff_id' => $staff->id, 'opening_float' => 100]);

        $this->assertSame('100.00', $drawer->opening_float);
        $this->assertNull($drawer->closed_at);
    }

    public function test_close_computes_expected_cash_from_completed_cash_payments_since_opening(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantB');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant, 'Branch B');
        $staff = $this->makeStaff($tenant, $branch, 'Cashier');

        $service = $this->app->make(CashDrawerService::class);
        $drawer = $service->open(['branch_id' => $branch->id, 'staff_id' => $staff->id, 'opening_float' => 100]);

        $order = \App\Models\Order::create(['branch_id' => $branch->id, 'order_number' => 'ORD-1', 'total_amount' => 50]);
        Payment::create([
            'order_id' => $order->id, 'branch_id' => $branch->id, 'payment_method' => 'cash',
            'amount' => 50, 'status' => 'completed', 'paid_at' => now(),
        ]);
        // A card payment should NOT count toward cash expected total.
        Payment::create([
            'order_id' => $order->id, 'branch_id' => $branch->id, 'payment_method' => 'card',
            'amount' => 999, 'status' => 'completed', 'paid_at' => now(),
        ]);

        $closed = $service->close($drawer, 150, 'End of shift');

        $this->assertSame('150.00', $closed->expected_cash);
        $this->assertSame('150.00', $closed->actual_cash);
        $this->assertSame('0.00', $closed->variance);
        $this->assertNotNull($closed->closed_at);
    }

    public function test_a_tenant_only_sees_their_own_branchs_drawers(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantC');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantD');

        Auth::login($ownerB);
        $branchB = $this->makeBranch($tenantB, 'Branch D');
        $staffB = $this->makeStaff($tenantB, $branchB, 'CashierB');
        $service = $this->app->make(CashDrawerService::class);
        $service->open(['branch_id' => $branchB->id, 'staff_id' => $staffB->id, 'opening_float' => 50]);

        Auth::login($ownerA);
        $results = $service->list([]);

        $this->assertSame(0, $results->total());
    }
}
