<?php

namespace Tests\Feature\Promotions;

use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PromotionServiceTest extends TestCase
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

    public function test_a_tenant_owner_only_sees_their_own_promotions(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantB');

        Promotion::create(['tenant_id' => $tenantA->id, 'name' => 'A Sale', 'type' => 'percentage', 'discount_value' => 10, 'start_at' => now()]);
        Promotion::create(['tenant_id' => $tenantB->id, 'name' => 'B Sale', 'type' => 'percentage', 'discount_value' => 10, 'start_at' => now()]);

        Auth::login($ownerA);
        $service = $this->app->make(PromotionService::class);
        $this->assertSame(1, $service->list([])->total());
        $this->assertSame('A Sale', $service->list([])->first()->name);

        Auth::login($ownerB);
        $this->assertSame(1, $service->list([])->total());
        $this->assertSame('B Sale', $service->list([])->first()->name);
    }

    public function test_create_assigns_the_resolved_tenant_id_not_a_spoofed_one(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB, ] = $this->makeTenantWithOwner('TenantB');

        Auth::login($ownerA);
        $service = $this->app->make(PromotionService::class);
        $request = Request::create('/', 'POST', ['tenant_id' => $tenantB->id]);

        $promotion = $service->create([
            'tenant_id' => $tenantB->id, // spoofed — should be overwritten
            'name' => 'Grand Opening',
            'type' => 'fixed_amount',
            'discount_value' => 5,
            'start_at' => now(),
        ], $request);

        $this->assertSame($tenantA->id, $promotion->tenant_id);
    }

    public function test_update_and_delete_go_through_the_repository(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        Auth::login($ownerA);

        $promotion = Promotion::create(['tenant_id' => $tenantA->id, 'name' => 'Sale', 'type' => 'percentage', 'discount_value' => 10, 'start_at' => now()]);
        $service = $this->app->make(PromotionService::class);

        $updated = $service->update($promotion, ['discount_value' => 20]);
        $this->assertEquals(20, $updated->discount_value);

        $service->delete($promotion);
        $this->assertNull(Promotion::find($promotion->id));
    }
}
