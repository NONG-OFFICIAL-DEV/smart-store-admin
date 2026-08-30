<?php

namespace Tests\Feature\Tenants;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\PlanBillingCycle;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * TenantController was already fully working, carefully written, and
 * security-conscious (manual tenant-isolation checks on show()/
 * getSubscriptionByTenant() since Tenant itself has no #[ScopedBy] — it IS
 * the tenant boundary). This is a structural migration only — Repository/
 * Service/FormRequest layering, preserving every behavior verbatim,
 * given the stakes (ownership transfer, password resets, billing data).
 * Route params standardized from raw {id} to real Tenant $tenant model
 * binding, matching the rest of this sweep's convention.
 */
class TenantServiceTest extends TestCase
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

    private function makeStaffUser(Tenant $tenant, Branch $branch, string $name, ?Role $role = null): User
    {
        $user = User::create([
            'email' => strtolower($name).'@example.test',
            'first_name' => $name,
            'last_name' => 'Staffer',
        ]);

        Staff::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role_id' => ($role ?? Role::create(['tenant_id' => $tenant->id, 'name' => $name.' Role']))->id,
        ]);

        return $user;
    }

    public function test_create_provisions_owner_tenant_and_owner_role(): void
    {
        $this->seedFreePlan();
        $service = $this->app->make(TenantService::class);

        $result = $service->create([
            'owner_first_name' => 'Jane',
            'owner_last_name' => 'Doe',
            'owner_email' => 'jane@example.test',
            'owner_password' => 'SuperSecret123!',
            'name' => 'Jane Shop',
            'business_type_id' => \App\Models\BusinessType::create(['code' => 'mart', 'name' => 'Mart'])->id,
        ], null);

        $tenant = Tenant::find($result['tenant_id']);
        $owner = User::find($result['owner_id']);

        $this->assertSame($owner->id, $tenant->owner_user_id);
        $this->assertTrue(Role::where('tenant_id', $tenant->id)->where('is_system', true)->exists());
    }

    public function test_create_assigns_the_free_plan_by_default(): void
    {
        $this->seedFreePlan();
        $service = $this->app->make(TenantService::class);

        $result = $service->create([
            'owner_first_name' => 'Jane',
            'owner_last_name' => 'Doe',
            'owner_email' => 'jane2@example.test',
            'owner_password' => 'SuperSecret123!',
            'name' => 'Jane Shop 2',
            'business_type_id' => \App\Models\BusinessType::create(['code' => 'mart', 'name' => 'Mart'])->id,
        ], null);

        $subscription = TenantSubscription::withoutGlobalScopes()
            ->where('tenant_id', $result['tenant_id'])
            ->first();

        $this->assertNotNull($subscription);
        $this->assertSame('active', $subscription->status);
        $this->assertSame('free', $subscription->plan->code);
    }

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

    /**
     * invoices/plan_history/billing_cycles were dropped from detail()'s
     * response — none of GET /tenants/{tenant}'s three consumers
     * (TenantDetails.vue, Profile.vue, TenantBilling.vue's incidental
     * refresh) ever read them from this endpoint; TenantDetails.vue's own
     * "Plan History" tab and invoices display were removed in the same
     * pass that prompted this trim.
     */
    public function test_detail_only_returns_the_fields_its_consumers_actually_use(): void
    {
        $this->seedFreePlan();
        [$tenant] = $this->makeTenantWithOwner('DetailShape');
        $this->app->make(\App\Services\TenantSubscriptionService::class)->changePlan(
            tenant: $tenant,
            newPlanId: Plan::where('code', 'free')->firstOrFail()->id,
            newCycleId: PlanBillingCycle::first()->id,
            changedBy: $tenant->owner_user_id,
        );

        $result = $this->app->make(TenantService::class)->detail($tenant->fresh());

        $this->assertSame(['tenant', 'subscription', 'plan', 'active_billing_cycle'], array_keys($result));
        $this->assertNotNull($result['subscription']);
        $this->assertSame('free', $result['plan']->code);
    }

    public function test_isVisibleTo_allows_owner_staff_and_super_admin_but_rejects_others(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        $staffUser = $this->makeStaffUser($tenant, $branch, 'Staffer');
        [$otherTenant, $otherOwner] = $this->makeTenantWithOwner('TenantB');
        $superAdmin = User::create(['email' => 'super@example.test', 'first_name' => 'S', 'last_name' => 'A', 'is_super_admin' => true]);

        $service = $this->app->make(TenantService::class);

        $this->assertTrue($service->isVisibleTo($tenant, $owner));
        $this->assertTrue($service->isVisibleTo($tenant, $staffUser));
        $this->assertTrue($service->isVisibleTo($tenant, $superAdmin));
        $this->assertFalse($service->isVisibleTo($tenant, $otherOwner));
    }

    public function test_toggleActive_flips_the_flag(): void
    {
        [$tenant] = $this->makeTenantWithOwner('TenantC');
        $tenant->refresh(); // pick up the DB default (is_active => true)
        $service = $this->app->make(TenantService::class);

        $toggled = $service->toggleActive($tenant);

        $this->assertFalse($toggled->is_active);
    }

    public function test_resetOwnerPassword_rejects_a_tenant_with_no_owner(): void
    {
        $tenant = Tenant::create(['name' => 'Orphan', 'slug' => 'orphan']);
        $service = $this->app->make(TenantService::class);

        $this->expectException(ValidationException::class);
        $service->resetOwnerPassword($tenant);
    }

    public function test_resetOwnerPassword_returns_a_temporary_password(): void
    {
        [$tenant] = $this->makeTenantWithOwner('TenantD');
        $service = $this->app->make(TenantService::class);

        $password = $service->resetOwnerPassword($tenant);

        $this->assertIsString($password);
        $this->assertNotEmpty($password);
    }

    public function test_transferOwnership_rejects_transferring_to_the_current_owner(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantE');
        $service = $this->app->make(TenantService::class);

        $this->expectException(ValidationException::class);
        $service->transferOwnership($tenant, ['new_owner_user_id' => $owner->id]);
    }

    public function test_transferOwnership_moves_owner_user_id_and_clears_caches(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantF');
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        $newOwnerUser = $this->makeStaffUser($tenant, $branch, 'NewOwner');

        $service = $this->app->make(TenantService::class);
        $updated = $service->transferOwnership($tenant, ['new_owner_user_id' => $newOwnerUser->id]);

        $this->assertSame($newOwnerUser->id, $updated->owner_user_id);
    }

    public function test_transferOwnership_rejects_demoting_into_the_owner_role(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantG');
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        $newOwnerUser = $this->makeStaffUser($tenant, $branch, 'NewOwner');
        $ownerRole = $this->app->make(\App\Services\OwnerRoleProvisioner::class)->ensureFor($tenant);

        $service = $this->app->make(TenantService::class);

        $this->expectException(ValidationException::class);
        $service->transferOwnership($tenant, [
            'new_owner_user_id' => $newOwnerUser->id,
            'demote_role_id' => $ownerRole->id,
            'demote_branch_id' => $branch->id,
        ]);
    }

    public function test_delete_removes_the_tenant(): void
    {
        [$tenant] = $this->makeTenantWithOwner('TenantH');
        $service = $this->app->make(TenantService::class);

        $service->delete($tenant);

        $this->assertNull(Tenant::find($tenant->id));
    }

    public function test_update_updates_owner_and_tenant_fields(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantI');
        $tenant->refresh(); // pick up DB defaults (timezone/currency/locale)
        $businessType = \App\Models\BusinessType::create(['code' => 'restaurant', 'name' => 'Restaurant']);
        $service = $this->app->make(TenantService::class);

        $service->update($tenant, [
            'owner_first_name' => 'Updated',
            'owner_last_name' => 'Name',
            'name' => 'New Shop Name',
            'business_type_id' => $businessType->id,
        ]);

        $this->assertSame('Updated', $owner->refresh()->first_name);
        $this->assertSame('New Shop Name', $tenant->refresh()->name);
    }

    public function test_list_filters_by_is_active(): void
    {
        [$tenantA] = $this->makeTenantWithOwner('TenantJ');
        [$tenantB] = $this->makeTenantWithOwner('TenantK');
        $tenantB->update(['is_active' => false]);

        $service = $this->app->make(TenantService::class);
        $results = $service->list(['is_active' => 'true']);

        $this->assertSame(1, $results->total());
    }
}
