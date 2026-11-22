<?php

namespace Tests\Feature\Loyalty;

use App\Http\Controllers\Api\LoyaltyCardController;
use App\Models\Customer;
use App\Models\LoyaltyReward;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LoyaltyCardTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LoyaltyCardControllerTest extends TestCase
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

    private function resolve(array $params): \Illuminate\Http\JsonResponse
    {
        $request = Request::create('/loyalty-card/resolve', 'POST', $params);

        return $this->app->make(LoyaltyCardController::class)->resolve($request);
    }

    public function test_a_valid_token_resolves_to_the_customer_and_rewards_ready_count(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);

        $customer = Customer::create(['tenant_id' => $tenant->id, 'first_name' => 'Sok', 'last_name' => 'Dara']);
        $customer->addPoints(10, null, 'adjust');
        LoyaltyReward::create(['tenant_id' => $tenant->id, 'name' => 'Free Coffee', 'points_cost' => 10, 'is_active' => true]);
        LoyaltyReward::create(['tenant_id' => $tenant->id, 'name' => 'Free Cake', 'points_cost' => 20, 'is_active' => true]);

        $token = $this->app->make(LoyaltyCardTokenService::class)->generate($customer->id);

        $response = $this->resolve(['token' => $token]);
        $data = $response->getData(true)['data'];

        $this->assertSame($customer->id, $data['customer_id']);
        $this->assertSame('Sok Dara', $data['name']);
        $this->assertSame(10, $data['loyalty_points']);
        $this->assertSame(1, $data['rewards_ready_count']);
    }

    public function test_an_expired_token_is_rejected(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $customer = Customer::create(['tenant_id' => $tenant->id, 'first_name' => 'Sok']);

        $response = $this->resolve(['token' => 'garbage.0000000000000000']);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_a_token_minted_for_another_tenants_customer_does_not_resolve(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');

        $customerB = Customer::create(['tenant_id' => $tenantB->id, 'first_name' => 'Dara']);
        $token = $this->app->make(LoyaltyCardTokenService::class)->generate($customerB->id);

        Auth::login($ownerA);
        $response = $this->resolve(['token' => $token]);

        $this->assertSame(404, $response->getStatusCode());
    }
}
