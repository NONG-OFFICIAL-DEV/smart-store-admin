<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\EnsureSubscriptionActive;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Covers both blocking paths: Tenant.is_active (the "Suspend Tenant" admin
 * action — previously had zero enforcement anywhere) and
 * TenantSubscription.status (suspended/cancelled). Both now return the
 * standard ApiResponse envelope with a stable `code` instead of a raw
 * response()->json(), so the frontend can translate the message instead
 * of displaying the backend's English string verbatim.
 */
class EnsureSubscriptionActiveTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithOwner(string $name, bool $isActive = true): array
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
            'is_active' => $isActive,
        ]);

        return [$tenant, $owner];
    }

    private function makeSubscription(Tenant $tenant, string $status): void
    {
        $plan = $this->app->make(PlanService::class)->create([
            'name' => 'Plan',
            'code' => 'PLAN-'.uniqid(),
            'price_usd' => 10,
            'seats' => 1,
            'storage_gb' => 1,
            'billing_cycles' => [
                ['label' => 'Monthly', 'months' => 1, 'discount_percent' => 0],
            ],
        ]);

        TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle_id' => $plan->billingCycles->first()->id,
            'status' => $status,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
    }

    private function handle(string $path = '/api/v1/dashboard/stats', string $method = 'GET'): mixed
    {
        $middleware = new EnsureSubscriptionActive;

        return $middleware->handle(Request::create($path, $method), fn () => response('ok'));
    }

    public function test_super_admin_always_bypasses(): void
    {
        $admin = User::create([
            'email' => 'admin@example.test', 'first_name' => 'Admin', 'last_name' => 'Admin', 'is_super_admin' => true,
        ]);
        Auth::login($admin);

        $this->assertSame(200, $this->handle()->getStatusCode());
    }

    public function test_active_tenant_with_active_subscription_passes_through(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('ActiveTenant');
        $this->makeSubscription($tenant, 'active');
        Auth::login($owner);

        $this->assertSame(200, $this->handle()->getStatusCode());
    }

    public function test_suspended_tenant_is_blocked_with_tenant_suspended_code(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('SuspendedTenant', isActive: false);
        $this->makeSubscription($tenant, 'active');
        Auth::login($owner);

        $response = $this->handle();

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('TENANT_SUSPENDED', $body['code']);
        $this->assertFalse($body['success']);
    }

    public function test_cancelled_subscription_is_blocked_with_subscription_status_blocked_code(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('CancelledSub');
        $this->makeSubscription($tenant, 'cancelled');
        Auth::login($owner);

        $response = $this->handle();

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('SUBSCRIPTION_STATUS_BLOCKED', $body['code']);
    }

    public function test_suspended_tenant_takes_precedence_over_subscription_status(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('BothBlocked', isActive: false);
        $this->makeSubscription($tenant, 'cancelled');
        Auth::login($owner);

        $response = $this->handle();

        $body = json_decode($response->getContent(), true);
        $this->assertSame('TENANT_SUSPENDED', $body['code']);
    }

    public function test_tenant_with_no_subscription_at_all_is_not_blocked(): void
    {
        [, $owner] = $this->makeTenantWithOwner('NoSubscription');
        Auth::login($owner);

        $this->assertSame(200, $this->handle()->getStatusCode());
    }

    #[DataProvider('selfServiceBillingRoutes')]
    public function test_subscription_status_blocked_tenant_can_still_reach_self_service_billing(string $path, string $method): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('SelfServiceRenew');
        $this->makeSubscription($tenant, 'cancelled');
        Auth::login($owner);

        $this->assertSame(200, $this->handle($path, $method)->getStatusCode());
    }

    public static function selfServiceBillingRoutes(): array
    {
        return [
            ['/api/v1/billing/plans', 'GET'],
            ['/api/v1/billing/change-plan', 'POST'],
            ['/api/v1/billing/renew', 'POST'],
        ];
    }

    public function test_suspended_tenant_cannot_use_self_service_billing_to_unlock_itself(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('SuspendedNoEscape', isActive: false);
        $this->makeSubscription($tenant, 'cancelled');
        Auth::login($owner);

        $response = $this->handle('/api/v1/billing/change-plan', 'POST');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('TENANT_SUSPENDED', json_decode($response->getContent(), true)['code']);
    }

    public function test_subscription_status_blocked_tenant_is_still_blocked_on_unrelated_routes(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('StillBlockedElsewhere');
        $this->makeSubscription($tenant, 'cancelled');
        Auth::login($owner);

        $response = $this->handle('/api/v1/dashboard/stats', 'GET');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('SUBSCRIPTION_STATUS_BLOCKED', json_decode($response->getContent(), true)['code']);
    }
}
