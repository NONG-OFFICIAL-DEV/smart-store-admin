<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Covers the Customer resource's first pass through the Repository/Service
 * migration (see .claude/skills/migrate-resource-to-repository). Exercises
 * the real CustomerRepository + TenantScope stack, same fixture style as
 * tests/Feature/Security/TenantScopeTest.php, rather than going through
 * jwt.auth/permission HTTP middleware (unrelated to this migration).
 */
class CustomerServiceTest extends TestCase
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

    private function makeCustomer(Tenant $tenant, string $firstName): Customer
    {
        return Customer::create([
            'tenant_id' => $tenant->id,
            'first_name' => $firstName,
            'last_name' => 'Customer',
        ]);
    }

    public function test_a_tenant_owner_only_sees_their_own_customers(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        $this->makeCustomer($tenantA, 'Alice');
        $this->makeCustomer($tenantA, 'Amy');
        $this->makeCustomer($tenantB, 'Bob');

        Auth::login($ownerA);
        $this->assertSame(2, Customer::count());
        $this->assertSame(3, Customer::withoutGlobalScopes()->count());

        $service = $this->app->make(CustomerService::class);
        $this->assertSame(2, $service->list([])->total());

        Auth::login($ownerB);
        $this->assertSame(1, Customer::count());
        $this->assertSame(1, $service->list([])->total());
    }

    public function test_create_assigns_the_resolved_tenant_id_not_a_spoofed_one(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerA);

        $service = $this->app->make(CustomerService::class);
        $customer = $service->create([
            'first_name' => 'Charlie',
            'tenant_id' => $tenantB->id,
        ], new Request());

        $this->assertSame($tenantA->id, $customer->tenant_id);
    }

    public function test_update_and_delete_go_through_the_repository(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $customer = $this->makeCustomer($tenantA, 'Dana');

        Auth::login($ownerA);
        $service = $this->app->make(CustomerService::class);

        $updated = $service->update($customer, ['last_name' => 'Updated']);
        $this->assertSame('Updated', $updated->last_name);

        $service->delete($customer);
        $this->assertNull(Customer::find($customer->id));
    }

    public function test_add_and_redeem_loyalty_points(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        $customer = $this->makeCustomer($tenantA, 'Erin');

        Auth::login($ownerA);
        $service = $this->app->make(CustomerService::class);

        $customer = $service->addLoyaltyPoints($customer, 50);
        $this->assertSame(50, $customer->loyalty_points);

        $result = $service->redeemLoyaltyPoints($customer, 20);
        $this->assertTrue($result['redeemed']);
        $this->assertSame(30, $result['customer']->loyalty_points);

        $result = $service->redeemLoyaltyPoints($result['customer'], 999);
        $this->assertFalse($result['redeemed']);
    }
}
