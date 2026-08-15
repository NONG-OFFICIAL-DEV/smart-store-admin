<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class TenantService extends BaseService
{
    public function __construct(
        TenantRepositoryInterface $repository,
        private PasswordService $passwordService,
        private OwnerRoleProvisioner $ownerRoleProvisioner,
        private TenantSubscriptionService $subscriptions,
        private RefreshTokenService $refreshTokenService,
    ) {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function isVisibleTo(Tenant $tenant, User $user): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        return $tenant->owner_user_id === $user->id
            || $user->staff()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists();
    }

    /**
     * @return array{tenant: Tenant, subscription: mixed, plan: mixed, active_billing_cycle: mixed, billing_cycles: mixed, invoices: mixed, plan_history: mixed}
     */
    public function detail(Tenant $tenant): array
    {
        $tenant->load([
            'owner',
            'businessType',
            'branches',
            'subscriptions.plan',
            'subscriptions.billingCycle',
            'invoices.paymentTransactions',
            'subscriptionPlanHistory.fromPlan',
            'subscriptionPlanHistory.toPlan',
        ]);

        $activeSubscription = $tenant->subscriptions
            ->whereIn('status', ['active', 'trial'])
            ->sortByDesc('created_at')
            ->first();

        $activeCycles = $activeSubscription?->plan?->billingCycles
            ->where('is_active', true)
            ->values();

        return [
            'tenant' => $tenant,
            'subscription' => $activeSubscription,
            'plan' => $activeSubscription?->plan,
            'active_billing_cycle' => $activeSubscription?->billingCycle,
            'billing_cycles' => $activeCycles,
            'invoices' => $tenant->invoices->sortByDesc('created_at')->values(),
            'plan_history' => $tenant->subscriptionPlanHistory->sortByDesc('changed_at')->values(),
        ];
    }

    public function editShape(Tenant $tenant): array
    {
        $tenant->load(['owner', 'businessType']);

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'business_type_id' => $tenant->business_type_id,
            'logo_url' => $tenant->logo_url,
            'primary_color' => $tenant->primary_color,
            'currency' => $tenant->currency,
            'locale' => $tenant->locale,
            'timezone' => $tenant->timezone,
            'owner' => [
                'first_name' => $tenant->owner?->first_name,
                'last_name' => $tenant->owner?->last_name,
                'email' => $tenant->owner?->email,
                'phone' => $tenant->owner?->phone,
            ],
        ];
    }

    public function toggleActive(Tenant $tenant): Tenant
    {
        $tenant->is_active = ! $tenant->is_active;
        $tenant->save();

        return $tenant->load('owner');
    }

    public function resetOwnerPassword(Tenant $tenant): string
    {
        $tenant->loadMissing('owner');

        if (! $tenant->owner) {
            throw ValidationException::withMessages([
                'owner' => 'Tenant has no owner account',
            ]);
        }

        return $this->passwordService->adminReset($tenant->owner);
    }

    /**
     * This is the ONLY other place (besides create()) allowed to write
     * tenants.owner_user_id — no normal user-management path may touch it.
     */
    public function transferOwnership(Tenant $tenant, array $validated): Tenant
    {
        if ($validated['new_owner_user_id'] === $tenant->owner_user_id) {
            throw ValidationException::withMessages([
                'new_owner_user_id' => 'This user is already the owner.',
            ]);
        }

        return DB::transaction(function () use ($tenant, $validated) {
            $oldOwnerId = $tenant->owner_user_id;
            $newOwnerId = $validated['new_owner_user_id'];

            if (! empty($validated['demote_role_id'])) {
                $demoteRole = Role::withoutGlobalScopes()
                    ->where('id', $validated['demote_role_id'])
                    ->where('tenant_id', $tenant->id)
                    ->first();

                if (! $demoteRole || $demoteRole->isOwnerRole()) {
                    throw ValidationException::withMessages([
                        'demote_role_id' => 'Invalid demotion role.',
                    ]);
                }

                Staff::withoutGlobalScopes()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'user_id' => $oldOwnerId],
                    [
                        'branch_id' => $validated['demote_branch_id'],
                        'role_id' => $demoteRole->id,
                        'is_active' => true,
                        'employee_code' => Staff::generateEmployeeCode($tenant->id),
                    ]
                );
            }

            $tenant->update(['owner_user_id' => $newOwnerId]);

            // The old owner's Staff record (if just created above) no
            // longer needs its own permission cache cleared here —
            // clearPermissionCache only affects the role-based cache,
            // which is what's changing for both users below.
            Cache::forget("user_perms_{$oldOwnerId}");
            Cache::forget("user_owns_tenant_{$oldOwnerId}");
            Cache::forget("user_perms_{$newOwnerId}");
            Cache::forget("user_owns_tenant_{$newOwnerId}");

            return $tenant->fresh()->load('owner');
        });
    }

    public function delete(Tenant $tenant): void
    {
        $tenant->delete();
    }

    /**
     * Tenant creation deliberately does NOT touch subscriptions/plans — a
     * tenant can exist with zero subscription rows until an admin
     * explicitly assigns one from the Subscriptions screen
     * (TenantSubscriptionController). The one exception is self-service
     * signup (see TenantController::store()), which assigns a default
     * plan itself right after calling this.
     *
     * $temporary controls whether the owner's password is flagged for a
     * forced change on first login — true for admin-created tenants
     * (the admin picked the password, not the owner), false for
     * self-registration (the owner chose their own real password).
     *
     * @return array{tenant_id: string, owner_id: string}
     */
    public function create(array $validated, ?string $logoUrl, bool $temporary = true): array
    {
        return DB::transaction(function () use ($validated, $logoUrl, $temporary) {
            $owner = User::create([
                'first_name' => $validated['owner_first_name'],
                'last_name' => $validated['owner_last_name'],
                'email' => $validated['owner_email'],
                'phone' => $validated['owner_phone'] ?? null,
                'is_active' => true,
            ]);

            $this->passwordService->applyPassword($owner, $validated['owner_password'], temporary: $temporary);

            $slug = $validated['slug'] ?? $this->generateUniqueSlug($validated['name']);

            $tenant = $this->repository->create([
                'name' => $validated['name'],
                'slug' => $slug,
                'business_type_id' => $validated['business_type_id'],
                'currency' => $validated['currency'] ?? 'USD',
                'locale' => $validated['locale'] ?? 'en-US',
                'timezone' => $validated['timezone'] ?? 'UTC',
                'primary_color' => $validated['primary_color'] ?? '#6366f1',
                'logo_url' => $logoUrl,
                'owner_user_id' => $owner->id,
                'is_active' => true,
            ]);

            // Provision the tenant's protected Owner role with every
            // current permission — this is the ONLY place besides
            // ownership transfer that Owner status is ever established.
            $this->ownerRoleProvisioner->ensureFor($tenant);

            return ['tenant_id' => $tenant->id, 'owner_id' => $owner->id];
        });
    }

    /**
     * The public self-service signup path (TenantController::store() when
     * called with no authenticated user) — on top of create(), this also
     * assigns the default free plan (a brand new tenant can't be left with
     * zero subscription rows the way an admin-created one deliberately can
     * be, since nobody's coming back later to assign one) and immediately
     * logs the new owner in.
     *
     * @return array{tenant_id: string, owner_id: string, token: string, token_type: string, expires_in: int, refresh_token: string, refresh_expires_in: int}
     */
    public function registerSelfService(array $validated, ?string $logoUrl, Request $request): array
    {
        $result = $this->create($validated, $logoUrl, temporary: false);
        $owner = User::findOrFail($result['owner_id']);

        $freePlan = Plan::where('code', 'free')->firstOrFail();
        $cycleId = $freePlan->billingCycles()->where('is_active', true)->value('id');

        $this->subscriptions->changePlan(
            tenant: Tenant::findOrFail($result['tenant_id']),
            newPlanId: $freePlan->id,
            newCycleId: $cycleId,
            changedBy: $owner->id,
            reason: 'Self-registration default plan',
        );

        $token = JWTAuth::fromUser($owner);
        $refreshToken = $this->refreshTokenService->issue($owner, $request);

        return [...$result, 'token' => $token, 'token_type' => 'bearer', 'expires_in' => JWTAuth::factory()->getTTL() * 60, ...$refreshToken];
    }

    /**
     * Owner handled separately via the transfer endpoint. Plan/subscription
     * changes are NOT done here — see TenantSubscriptionService, the only
     * place a tenant's plan changes.
     */
    public function update(Tenant $tenant, array $validated): void
    {
        DB::transaction(function () use ($tenant, $validated) {
            $tenant->owner()->update([
                'first_name' => $validated['owner_first_name'],
                'last_name' => $validated['owner_last_name'],
                'phone' => $validated['owner_phone'] ?? null,
            ]);

            $tenant->update([
                'name' => $validated['name'],
                'slug' => $validated['slug'] ?? $tenant->slug,
                'business_type_id' => $validated['business_type_id'],
                'logo_url' => $validated['logo_url'] ?? $tenant->logo_url,
                'primary_color' => $validated['primary_color'] ?? $tenant->primary_color,
                'is_active' => $validated['is_active'] ?? $tenant->is_active,
                'currency' => $validated['currency'] ?? $tenant->currency,
                'locale' => $validated['locale'] ?? $tenant->locale,
                'timezone' => $validated['timezone'] ?? $tenant->timezone,
            ]);
        });
    }

    /**
     * TRIAL -> ACTIVE, called when the first invoice is paid / trial
     * converts. Not currently routed anywhere — was meant to be called by
     * a payment webhook handler that was never built (the empty
     * PaymentWebhookController this app previously had was dead/unrouted
     * and has since been removed). Kept here, unrouted, for whenever that
     * webhook integration is actually built — do not treat its absence
     * from routes/api.php as a bug to fix in this migration.
     */
    public function activateSubscription(Tenant $tenant): void
    {
        $subscription = $tenant->activeSubscription;

        if (! $subscription || $subscription->status !== 'trial') {
            return;
        }

        $subscription->update([
            'status' => 'active',
            'trial_ends_at' => null,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
    }

    /**
     * Same tenant-isolation rule as detail() — this exposes invoices/plan
     * history, so it must not be readable across tenants.
     */
    public function subscriptionDetail(Tenant $tenant): array
    {
        $tenant->load([
            'subscriptions.plan.billingCycles',
            'subscriptions.plan.features',
            'subscriptions.billingCycle',
            'invoices.paymentTransactions',
            'subscriptionPlanHistory.fromPlan',
            'subscriptionPlanHistory.toPlan',
            'subscriptionPlanHistory.billingCycle',
        ]);

        $activeSubscription = $tenant->subscriptions
            ->whereIn('status', ['active', 'trial'])
            ->sortByDesc('created_at')
            ->first();

        return [
            'subscription' => $activeSubscription,
            'plan' => $activeSubscription?->plan,
            'active_billing_cycle' => $activeSubscription?->billingCycle,
            'billing_cycles' => $activeSubscription?->plan?->billingCycles->where('is_active', true)->values(),
            'invoices' => $tenant->invoices->sortByDesc('created_at')->values(),
            'plan_history' => $tenant->subscriptionPlanHistory->sortByDesc('changed_at')->values(),
        ];
    }

    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $count = Tenant::where('slug', 'like', $slug.'%')->count();

        return $count ? "{$slug}-{$count}" : $slug;
    }
}
