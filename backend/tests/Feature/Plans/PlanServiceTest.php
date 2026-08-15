<?php

namespace Tests\Feature\Plans;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * PlanController was already fully working (unlike most of this sweep's
 * dead stubs) — this migration is a structural move onto Repository/
 * Service/FormRequest, preserving the create/update nested billing_cycles
 * + features sync logic and the active-subscription delete guard
 * verbatim. Also fixed: the manually-registered toggle-active route used
 * {id} while the controller expected a Plan $plan binding — a param-name
 * mismatch (same bug class as BusinessType's {businessType} vs
 * {business_type}).
 */
class PlanServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_persists_plan_with_billing_cycles_and_features(): void
    {
        $service = $this->app->make(PlanService::class);
        $plan = $service->create([
            'name' => 'Pro',
            'code' => 'PRO',
            'price_usd' => 29,
            'seats' => 5,
            'storage_gb' => 10,
            'billing_cycles' => [
                ['label' => 'Monthly', 'months' => 1, 'discount_percent' => 0],
                ['label' => 'Yearly', 'months' => 12, 'discount_percent' => 20],
            ],
            'features' => [
                ['key' => 'products_limit', 'en' => 'Up to 100 products'],
            ],
        ]);

        $this->assertSame('pro', $plan->code);
        $this->assertCount(2, $plan->billingCycles);
        $this->assertCount(1, $plan->features);
    }

    public function test_update_removes_dropped_cycles_and_upserts_the_rest(): void
    {
        $service = $this->app->make(PlanService::class);
        $plan = $service->create([
            'name' => 'Pro', 'code' => 'PRO2', 'price_usd' => 29, 'seats' => 5, 'storage_gb' => 10,
            'billing_cycles' => [
                ['label' => 'Monthly', 'months' => 1, 'discount_percent' => 0],
                ['label' => 'Yearly', 'months' => 12, 'discount_percent' => 20],
            ],
        ]);
        $monthlyId = $plan->billingCycles()->where('months', 1)->first()->id;

        $updated = $service->update($plan, [
            'name' => 'Pro', 'code' => 'PRO2', 'price_usd' => 39, 'seats' => 5, 'storage_gb' => 10,
            'billing_cycles' => [
                ['id' => $monthlyId, 'label' => 'Monthly (updated)', 'months' => 1, 'discount_percent' => 5],
            ],
        ]);

        $this->assertCount(1, $updated->billingCycles);
        $this->assertSame('Monthly (updated)', $updated->billingCycles->first()->label);
        $this->assertSame('39.00', $updated->price_usd);
    }

    public function test_delete_is_blocked_when_plan_has_active_subscriptions(): void
    {
        $service = $this->app->make(PlanService::class);
        $plan = $service->create([
            'name' => 'Pro', 'code' => 'PRO3', 'price_usd' => 29, 'seats' => 5, 'storage_gb' => 10,
            'billing_cycles' => [['label' => 'Monthly', 'months' => 1, 'discount_percent' => 0]],
        ]);

        $owner = User::create(['email' => 'owner@example.test', 'first_name' => 'O', 'last_name' => 'W']);
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't-'.substr((string) $owner->id, 0, 8), 'owner_user_id' => $owner->id]);
        TenantSubscription::create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => 'active']);

        $this->expectException(ValidationException::class);
        $service->delete($plan);
    }

    public function test_delete_succeeds_when_no_active_subscriptions(): void
    {
        $service = $this->app->make(PlanService::class);
        $plan = $service->create([
            'name' => 'Pro', 'code' => 'PRO4', 'price_usd' => 29, 'seats' => 5, 'storage_gb' => 10,
            'billing_cycles' => [['label' => 'Monthly', 'months' => 1, 'discount_percent' => 0]],
        ]);

        $service->delete($plan);

        $this->assertNull(Plan::find($plan->id));
    }

    public function test_toggle_active_flips_the_flag(): void
    {
        $service = $this->app->make(PlanService::class);
        $plan = $service->create([
            'name' => 'Pro', 'code' => 'PRO5', 'price_usd' => 29, 'seats' => 5, 'storage_gb' => 10, 'is_active' => true,
            'billing_cycles' => [['label' => 'Monthly', 'months' => 1, 'discount_percent' => 0]],
        ]);

        $toggled = $service->toggleActive($plan);

        $this->assertFalse($toggled->is_active);
    }

    public function test_public_plans_excludes_inactive_by_default(): void
    {
        $service = $this->app->make(PlanService::class);
        $service->create([
            'name' => 'Active', 'code' => 'ACT', 'price_usd' => 10, 'seats' => 1, 'storage_gb' => 1, 'is_active' => true,
            'billing_cycles' => [['label' => 'Monthly', 'months' => 1, 'discount_percent' => 0]],
        ]);
        $service->create([
            'name' => 'Inactive', 'code' => 'INACT', 'price_usd' => 10, 'seats' => 1, 'storage_gb' => 1, 'is_active' => false,
            'billing_cycles' => [['label' => 'Monthly', 'months' => 1, 'discount_percent' => 0]],
        ]);

        $this->assertCount(1, $service->publicPlans(includeInactive: false));
        $this->assertCount(2, $service->publicPlans(includeInactive: true));
    }
}
