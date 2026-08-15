<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InventoryStockService;
use App\Services\InventoryTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * InventoryTransactionController::index()'s search filtered `type`/
 * `reference` — neither column exists (real: transaction_type,
 * reference_type/reference_id).
 */
class InventoryTransactionServiceTest extends TestCase
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

    public function test_filtering_by_transaction_type_works_against_the_real_column(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        $ingredient = Ingredient::create(['tenant_id' => $tenant->id, 'name' => 'Flour', 'unit' => 'kg']);

        $stockService = $this->app->make(InventoryStockService::class);
        $stockService->adjust($branch->id, $ingredient->id, 10, 'purchase');
        $stockService->adjust($branch->id, $ingredient->id, -2, 'waste');

        $service = $this->app->make(InventoryTransactionService::class);
        $results = $service->list(['transaction_type' => 'waste']);

        $this->assertSame(1, $results->total());
        $this->assertSame('waste', $results->first()->transaction_type);
    }

    public function test_a_tenant_only_sees_their_own_transactions(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantB');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantC');

        Auth::login($ownerB);
        $branchB = Branch::create(['tenant_id' => $tenantB->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        $ingredientB = Ingredient::create(['tenant_id' => $tenantB->id, 'name' => 'Sugar', 'unit' => 'kg']);
        $this->app->make(InventoryStockService::class)->adjust($branchB->id, $ingredientB->id, 5, 'purchase');

        Auth::login($ownerA);
        $service = $this->app->make(InventoryTransactionService::class);

        $this->assertSame(0, $service->list([])->total());
    }
}
