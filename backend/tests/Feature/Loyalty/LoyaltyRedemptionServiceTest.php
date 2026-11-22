<?php

namespace Tests\Feature\Loyalty;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LoyaltyRedemptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class LoyaltyRedemptionServiceTest extends TestCase
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

    // loyalty_points isn't mass-assignable (only Customer::addPoints()/
    // redeemPoints() are allowed to move it, each logging a ledger row) —
    // so fixtures needing a starting balance must earn it the same way.
    private function makeCustomerWithPoints(Tenant $tenant, int $points): Customer
    {
        $customer = Customer::create(['tenant_id' => $tenant->id, 'first_name' => 'Sok']);
        if ($points > 0) {
            $customer->addPoints($points, null, 'earn');
        }

        return $customer->refresh();
    }

    public function test_requesting_a_redemption_deducts_points_immediately(): void
    {
        [$tenant] = $this->makeTenantWithOwner('TenantA');
        $customer = $this->makeCustomerWithPoints($tenant, 10);
        $reward = LoyaltyReward::create(['tenant_id' => $tenant->id, 'name' => 'Free Coffee', 'points_cost' => 10, 'is_active' => true]);

        $service = $this->app->make(LoyaltyRedemptionService::class);
        $redemption = $service->requestRedemption($customer, $reward);

        $this->assertSame('pending', $redemption->status);
        $this->assertSame(6, strlen($redemption->code));
        $this->assertSame(0, $customer->refresh()->loyalty_points);
        $this->assertNotNull($redemption->loyalty_transaction_id);
    }

    public function test_requesting_more_points_than_available_throws(): void
    {
        [$tenant] = $this->makeTenantWithOwner('TenantA');
        $customer = $this->makeCustomerWithPoints($tenant, 2);
        $reward = LoyaltyReward::create(['tenant_id' => $tenant->id, 'name' => 'Free Coffee', 'points_cost' => 10, 'is_active' => true]);

        $service = $this->app->make(LoyaltyRedemptionService::class);

        $this->expectException(RuntimeException::class);
        $service->requestRedemption($customer, $reward);
    }

    public function test_staff_confirming_marks_it_redeemed(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        $customer = $this->makeCustomerWithPoints($tenant, 10);
        $reward = LoyaltyReward::create(['tenant_id' => $tenant->id, 'name' => 'Free Coffee', 'points_cost' => 10, 'is_active' => true]);

        $service = $this->app->make(LoyaltyRedemptionService::class);
        $redemption = $service->requestRedemption($customer, $reward);

        $confirmed = $service->confirm($redemption, $owner);

        $this->assertSame('redeemed', $confirmed->status);
        $this->assertSame($owner->id, $confirmed->redeemed_by_staff_id);
        $this->assertNotNull($confirmed->redeemed_at);
    }

    public function test_confirming_with_an_order_id_stamps_it_onto_the_redemption(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        $customer = $this->makeCustomerWithPoints($tenant, 10);
        $reward = LoyaltyReward::create(['tenant_id' => $tenant->id, 'name' => 'Free Coffee', 'points_cost' => 10, 'is_active' => true]);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main Branch', 'address_line1' => '123 Main St', 'city' => 'Phnom Penh']);
        $order = Order::create(['branch_id' => $branch->id, 'order_number' => 'ORD-1', 'total_amount' => 10]);

        $service = $this->app->make(LoyaltyRedemptionService::class);
        $redemption = $service->requestRedemption($customer, $reward);

        $confirmed = $service->confirm($redemption, $owner, $order->id, $branch->id);

        $this->assertSame($order->id, $confirmed->order_id);
        $this->assertSame($branch->id, $confirmed->branch_id);
    }

    public function test_discount_instruction_reflects_the_rewards_configured_type(): void
    {
        [$tenant] = $this->makeTenantWithOwner('TenantA');
        $percentReward = LoyaltyReward::create([
            'tenant_id' => $tenant->id, 'name' => '10% Off', 'points_cost' => 10,
            'type' => 'discount_percent', 'discount_value' => 10,
        ]);
        $amountReward = LoyaltyReward::create([
            'tenant_id' => $tenant->id, 'name' => '$2 Off', 'points_cost' => 10,
            'type' => 'discount_amount', 'discount_value' => 2,
        ]);
        $unconfiguredReward = LoyaltyReward::create(['tenant_id' => $tenant->id, 'name' => 'Mystery', 'points_cost' => 10]);

        $service = $this->app->make(LoyaltyRedemptionService::class);

        $this->assertSame(['type' => 'discount_percent', 'percent' => 10.0], $service->discountInstruction($percentReward));
        $this->assertSame(['type' => 'discount_amount', 'amount' => 2.0], $service->discountInstruction($amountReward));
        $this->assertSame(['type' => null], $service->discountInstruction($unconfiguredReward));
    }

    public function test_confirming_twice_throws(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        $customer = $this->makeCustomerWithPoints($tenant, 10);
        $reward = LoyaltyReward::create(['tenant_id' => $tenant->id, 'name' => 'Free Coffee', 'points_cost' => 10, 'is_active' => true]);

        $service = $this->app->make(LoyaltyRedemptionService::class);
        $redemption = $service->requestRedemption($customer, $reward);
        $service->confirm($redemption, $owner);

        $this->expectException(RuntimeException::class);
        $service->confirm($redemption->refresh(), $owner);
    }

    public function test_cancelling_a_pending_redemption_refunds_the_points(): void
    {
        [$tenant] = $this->makeTenantWithOwner('TenantA');
        $customer = $this->makeCustomerWithPoints($tenant, 10);
        $reward = LoyaltyReward::create(['tenant_id' => $tenant->id, 'name' => 'Free Coffee', 'points_cost' => 10, 'is_active' => true]);

        $service = $this->app->make(LoyaltyRedemptionService::class);
        $redemption = $service->requestRedemption($customer, $reward);
        $this->assertSame(0, $customer->refresh()->loyalty_points);

        $cancelled = $service->cancel($redemption);

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame(10, $customer->refresh()->loyalty_points);
    }

    public function test_an_expired_pending_redemption_is_refunded_and_reported_as_expired_on_lookup(): void
    {
        [$tenant] = $this->makeTenantWithOwner('TenantA');
        $customer = $this->makeCustomerWithPoints($tenant, 10);
        $reward = LoyaltyReward::create(['tenant_id' => $tenant->id, 'name' => 'Free Coffee', 'points_cost' => 10, 'is_active' => true]);

        $service = $this->app->make(LoyaltyRedemptionService::class);
        $redemption = $service->requestRedemption($customer, $reward);
        $redemption->update(['expires_at' => now()->subMinute()]);

        $found = $service->findByCode($redemption->code);

        $this->assertSame('expired', $found->status);
        $this->assertSame(10, $customer->refresh()->loyalty_points);
    }

    public function test_only_one_loyalty_transaction_is_used_to_redeem_not_the_earlier_earn(): void
    {
        [$tenant] = $this->makeTenantWithOwner('TenantA');
        $customer = Customer::create(['tenant_id' => $tenant->id, 'first_name' => 'Sok']);
        $customer->addPoints(20, null, 'earn');
        $reward = LoyaltyReward::create(['tenant_id' => $tenant->id, 'name' => 'Free Coffee', 'points_cost' => 10, 'is_active' => true]);

        $service = $this->app->make(LoyaltyRedemptionService::class);
        $redemption = $service->requestRedemption($customer, $reward);

        $linked = LoyaltyTransaction::find($redemption->loyalty_transaction_id);
        $this->assertSame('redeem', $linked->type);
        $this->assertSame(-10, $linked->points);
    }
}
