<?php

namespace Tests\Feature\LoyaltyTransactions;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LoyaltyTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * LoyaltyTransactionController::store/show/update/destroy were empty
 * stubs (index/show are the only routed actions — an immutable ledger, by
 * design, same as Refund). The real writes already happen correctly via
 * Customer::addPoints()/redeemPoints() (pre-existing, untouched here) —
 * confirmed those don't go through the dead LoyaltyTransaction::store().
 */
class LoyaltyTransactionServiceTest extends TestCase
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

    public function test_byCustomer_lists_only_that_customers_ledger_entries(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);

        $customerA = Customer::create(['tenant_id' => $tenant->id, 'first_name' => 'A', 'last_name' => 'One', 'phone' => '1']);
        $customerB = Customer::create(['tenant_id' => $tenant->id, 'first_name' => 'B', 'last_name' => 'Two', 'phone' => '2']);

        $customerA->addPoints(10);
        $customerB->addPoints(5);

        $service = $this->app->make(LoyaltyTransactionService::class);
        $results = $service->byCustomer($customerA->id, []);

        $this->assertSame(1, $results->total());
        $this->assertSame($customerA->id, $results->first()->customer_id);
    }

    public function test_a_tenant_only_sees_their_own_customers_transactions(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantB');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantC');

        Auth::login($ownerB);
        $customerB = Customer::create(['tenant_id' => $tenantB->id, 'first_name' => 'B', 'last_name' => 'Two', 'phone' => '2']);
        $customerB->addPoints(10);

        Auth::login($ownerA);
        $service = $this->app->make(LoyaltyTransactionService::class);

        $this->assertSame(0, $service->list([])->total());
    }
}
