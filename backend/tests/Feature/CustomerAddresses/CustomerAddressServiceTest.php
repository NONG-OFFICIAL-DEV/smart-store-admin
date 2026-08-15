<?php

namespace Tests\Feature\CustomerAddresses;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CustomerAddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * customer_addresses had no #[ScopedBy] at all — only linked via
 * customer_id -> customers.tenant_id — and show/update/destroy were
 * registered SHALLOW (Route::apiResource('addresses', ...)->only(['show',
 * 'update','destroy']), no customers/{customer} prefix), while update/
 * destroy were fully implemented (not stubs, unlike most other bugs found
 * in this sweep). Any user with customers.manage in any tenant could
 * view/edit/delete any other tenant's customer address directly by id —
 * live and exploitable, not theoretical. Also fixed: $fillable was missing
 * state/postal_code/country entirely (present in the schema), and the old
 * CustomerAddress::store()'s request field list omitted `city` and
 * included `country` despite it not being fillable — some fields were
 * silently unsettable via the API.
 */
class CustomerAddressServiceTest extends TestCase
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

    private function makeCustomer(Tenant $tenant, string $name): Customer
    {
        return Customer::create([
            'tenant_id' => $tenant->id,
            'first_name' => $name,
            'last_name' => 'Customer',
            'phone' => '0123456789',
        ]);
    }

    public function test_a_tenant_cannot_view_or_modify_another_tenants_address_directly_by_id(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerB);
        $customerB = $this->makeCustomer($tenantB, 'Bob');
        $addressB = CustomerAddress::create([
            'customer_id' => $customerB->id,
            'address_line1' => '123 B St',
            'city' => 'Phnom Penh',
        ]);

        Auth::login($ownerA);
        $this->assertNull(CustomerAddress::find($addressB->id));
        $this->assertNotNull(CustomerAddress::withoutGlobalScopes()->find($addressB->id));
    }

    public function test_create_persists_all_real_columns_including_previously_dropped_fields(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantC');
        Auth::login($owner);
        $customer = $this->makeCustomer($tenant, 'Cara');

        $service = $this->app->make(CustomerAddressService::class);
        $address = $service->create($customer, [
            'address_line1' => '456 Main St',
            'city' => 'Siem Reap',
            'state' => 'Siem Reap Province',
            'postal_code' => '17000',
            'country' => 'KH',
        ]);

        $this->assertSame('Siem Reap', $address->city);
        $this->assertSame('Siem Reap Province', $address->state);
        $this->assertSame('17000', $address->postal_code);
        $this->assertSame('KH', $address->country);
    }

    public function test_only_one_default_address_per_customer(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantD');
        Auth::login($owner);
        $customer = $this->makeCustomer($tenant, 'Dara');

        $service = $this->app->make(CustomerAddressService::class);
        $first = $service->create($customer, ['address_line1' => 'First', 'city' => 'Phnom Penh', 'is_default' => true]);
        $second = $service->create($customer, ['address_line1' => 'Second', 'city' => 'Phnom Penh', 'is_default' => true]);

        $this->assertFalse($first->refresh()->is_default);
        $this->assertTrue($second->refresh()->is_default);
    }

    public function test_setting_default_on_update_clears_other_addresses_for_the_same_customer(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantE');
        Auth::login($owner);
        $customer = $this->makeCustomer($tenant, 'Eli');

        $service = $this->app->make(CustomerAddressService::class);
        $first = $service->create($customer, ['address_line1' => 'First', 'city' => 'Phnom Penh', 'is_default' => true]);
        $second = $service->create($customer, ['address_line1' => 'Second', 'city' => 'Phnom Penh', 'is_default' => false]);

        $service->update($second, ['is_default' => true]);

        $this->assertFalse($first->refresh()->is_default);
        $this->assertTrue($second->refresh()->is_default);
    }

    public function test_index_defaults_to_sorting_by_label_without_a_created_at_column(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantF');
        Auth::login($owner);
        $customer = $this->makeCustomer($tenant, 'Finn');

        $service = $this->app->make(CustomerAddressService::class);
        $service->create($customer, ['address_line1' => 'A', 'city' => 'Phnom Penh', 'label' => 'Work']);
        $service->create($customer, ['address_line1' => 'B', 'city' => 'Phnom Penh', 'label' => 'Home']);

        $results = $service->list(['customer_id' => $customer->id]);

        $this->assertSame(2, $results->total());
    }
}
