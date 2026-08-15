<?php

namespace Tests\Feature\Auth;

use App\Models\BusinessType;
use App\Models\Plan;
use App\Models\PlanBillingCycle;
use App\Models\RefreshToken;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class RegisterServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedFreePlan(): Plan
    {
        $plan = Plan::create(['name' => 'Free', 'code' => 'free', 'price_usd' => 0, 'is_active' => true]);

        PlanBillingCycle::create([
            'plan_id' => $plan->id,
            'label' => 'Monthly',
            'months' => 1,
            'discount_percent' => 0,
            'is_active' => true,
        ]);

        return $plan;
    }

    private function validPayload(): array
    {
        return [
            'owner_first_name' => 'New',
            'owner_last_name' => 'Owner',
            'owner_email' => 'newowner@example.test',
            'owner_password' => 'SuperSecret123!',
            'name' => 'New Shop',
            'business_type_id' => BusinessType::create(['code' => 'mart', 'name' => 'Mart'])->id,
        ];
    }

    public function test_registers_a_tenant_with_owner_role_free_plan_and_a_working_token_pair(): void
    {
        $this->seedFreePlan();
        $service = $this->app->make(TenantService::class);

        $result = $service->registerSelfService($this->validPayload(), null, Request::create('/business-register'));

        $tenant = Tenant::findOrFail($result['tenant_id']);
        $owner = User::findOrFail($result['owner_id']);

        $this->assertSame($owner->id, $tenant->owner_user_id);
        $this->assertTrue(Role::where('tenant_id', $tenant->id)->where('is_system', true)->exists());

        $subscription = TenantSubscription::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($subscription);
        $this->assertSame('active', $subscription->status);

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertSame(1, RefreshToken::where('user_id', $owner->id)->whereNull('revoked_at')->count());
    }

    public function test_the_owner_password_is_not_flagged_for_a_forced_change(): void
    {
        $this->seedFreePlan();
        $service = $this->app->make(TenantService::class);

        $result = $service->registerSelfService($this->validPayload(), null, Request::create('/business-register'));

        $owner = User::findOrFail($result['owner_id']);

        // Self-registration means the owner chose their OWN real password —
        // unlike an admin-created tenant, there's no reason to force a
        // change on first login.
        $this->assertFalse($owner->must_change_password);
    }

    public function test_registration_fails_cleanly_when_no_free_plan_is_seeded(): void
    {
        $service = $this->app->make(TenantService::class);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $service->registerSelfService($this->validPayload(), null, Request::create('/business-register'));
    }
}
