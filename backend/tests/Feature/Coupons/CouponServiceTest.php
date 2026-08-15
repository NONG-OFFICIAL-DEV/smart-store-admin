<?php

namespace Tests\Feature\Coupons;

use App\Models\Coupon;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CouponService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * coupons has neither tenant_id nor branch_id and the model had NO
 * #[ScopedBy] at all — tenant-owned only indirectly via
 * promotion_id -> promotions.tenant_id, reachable via a flat
 * Route::apiResource('coupons', ...) with no promotion/tenant context in
 * the URL. Before the TenantScope fix, any user with promotions.manage in
 * ANY tenant could view/edit/delete every other tenant's coupons directly
 * by id. See TenantScope::apply()'s 'coupons' branch.
 */
class CouponServiceTest extends TestCase
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

    private function makePromotion(Tenant $tenant, string $name = 'Sale'): Promotion
    {
        return Promotion::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'type' => 'percentage',
            'discount_value' => 10,
            'start_at' => now(),
        ]);
    }

    public function test_a_tenant_cannot_see_another_tenants_coupon_via_direct_lookup(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerB);
        $promoB = $this->makePromotion($tenantB);
        $couponB = Coupon::create(['promotion_id' => $promoB->id, 'code' => 'SAVE10B']);

        Auth::login($ownerA);
        $this->assertNull(Coupon::find($couponB->id));
        $this->assertNotNull(Coupon::withoutGlobalScopes()->find($couponB->id));
    }

    public function test_a_tenant_owner_only_sees_coupons_for_their_own_promotions(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerA);
        $promoA = $this->makePromotion($tenantA);
        $service = $this->app->make(CouponService::class);
        $service->create(['promotion_id' => $promoA->id, 'code' => 'SAVE10A']);
        $service->create(['promotion_id' => $promoA->id, 'code' => 'SAVE20A']);

        Auth::login($ownerB);
        $promoB = $this->makePromotion($tenantB);
        $service->create(['promotion_id' => $promoB->id, 'code' => 'SAVE10B']);

        Auth::login($ownerA);
        $this->assertSame(2, $service->list([])->total());
        $this->assertSame(2, $service->byPromotion($promoA, [])->total());

        Auth::login($ownerB);
        $this->assertSame(1, $service->list([])->total());
    }

    public function test_create_rejects_a_cross_tenant_promotion_id(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, ] = $this->makeTenantWithOwner('TenantB');
        $promoB = $this->makePromotion($tenantB);

        Auth::login($ownerA);
        $service = $this->app->make(CouponService::class);

        $this->expectException(ModelNotFoundException::class);
        $service->create(['promotion_id' => $promoB->id, 'code' => 'STOLEN']);
    }

    public function test_code_is_auto_generated_when_omitted(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);
        $promo = $this->makePromotion($tenantA);
        $service = $this->app->make(CouponService::class);

        $coupon = $service->create(['promotion_id' => $promo->id]);

        $this->assertNotEmpty($coupon->code);
        $this->assertSame(8, strlen($coupon->code));
    }

    public function test_update_and_delete_go_through_the_repository(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);
        $promo = $this->makePromotion($tenantA);
        $service = $this->app->make(CouponService::class);

        $coupon = $service->create(['promotion_id' => $promo->id, 'code' => 'ORIGINAL']);
        $updated = $service->update($coupon, ['usage_limit' => 5]);
        $this->assertSame(5, $updated->usage_limit);

        $service->delete($coupon);
        $this->assertNull(Coupon::find($coupon->id));
    }
}
