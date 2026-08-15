<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\InventoryStock;
use App\Models\InventoryTransaction;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InventoryStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * InventoryStockController::store/show/update/destroy were empty stubs,
 * and routes referencing byBranch/byIngredient/adjust/report pointed at
 * controller methods that didn't exist at all. The real logic
 * (InventoryStock::adjust() — stock + ledger + low-stock notification)
 * was dead (never routed) and is now in InventoryStockService::adjust(),
 * reused by both the new controller action AND PurchaseOrder::receive()
 * (which now calls it via a thin static delegator on the model, so
 * there's one implementation instead of two). Also fixed: the old
 * Notification::create() call double-JSON-encoded `data` (manually
 * json_encode()'d a value that Eloquent's `array` cast would encode again).
 */
class InventoryStockServiceTest extends TestCase
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

    private function makeBranch(Tenant $tenant, string $name): Branch
    {
        return Branch::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'address_line1' => '123 Main St',
            'city' => 'Phnom Penh',
        ]);
    }

    private function makeIngredient(Tenant $tenant, string $name, ?float $reorderPoint = null): Ingredient
    {
        return Ingredient::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'unit' => 'kg',
            'reorder_point' => $reorderPoint,
        ]);
    }

    public function test_adjust_creates_stock_and_ledgers_the_movement(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant, 'Branch A');
        $ingredient = $this->makeIngredient($tenant, 'Flour');

        $service = $this->app->make(InventoryStockService::class);
        $stock = $service->adjust($branch->id, $ingredient->id, 10, 'purchase');

        $this->assertSame('10.0000', $stock->quantity_on_hand);
        $this->assertSame(1, InventoryTransaction::where('ingredient_id', $ingredient->id)->count());
    }

    public function test_adjust_accumulates_on_existing_stock(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantB');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant, 'Branch B');
        $ingredient = $this->makeIngredient($tenant, 'Sugar');

        $service = $this->app->make(InventoryStockService::class);
        $service->adjust($branch->id, $ingredient->id, 10, 'purchase');
        $stock = $service->adjust($branch->id, $ingredient->id, -3, 'usage');

        $this->assertSame('7.0000', $stock->quantity_on_hand);
        $this->assertSame(2, InventoryTransaction::where('ingredient_id', $ingredient->id)->count());
    }

    public function test_adjust_fires_a_low_stock_notification_once_at_or_below_reorder_point(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantC');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant, 'Branch C');
        $ingredient = $this->makeIngredient($tenant, 'Milk', reorderPoint: 5);

        $service = $this->app->make(InventoryStockService::class);
        $service->adjust($branch->id, $ingredient->id, 10, 'purchase');
        $this->assertSame(0, Notification::where('type', 'low_stock')->count());

        $service->adjust($branch->id, $ingredient->id, -6, 'usage');

        $notification = Notification::where('type', 'low_stock')->first();
        $this->assertNotNull($notification);
        // data is array-cast — must be a real array, not a double-encoded JSON string.
        $this->assertSame($ingredient->id, $notification->data['ingredient_id']);
    }

    public function test_a_tenant_only_sees_their_own_branchs_stock(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantD');
        [$tenantB, $ownerB] = $this->makeTenantWithOwner('TenantE');

        Auth::login($ownerB);
        $branchB = $this->makeBranch($tenantB, 'Branch E');
        $ingredientB = $this->makeIngredient($tenantB, 'Coffee Beans');
        $service = $this->app->make(InventoryStockService::class);
        $service->adjust($branchB->id, $ingredientB->id, 5, 'purchase');

        Auth::login($ownerA);
        $results = $service->list([]);

        $this->assertSame(0, $results->total());
    }

    public function test_available_quantity_subtracts_reserved_from_on_hand(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantF');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant, 'Branch F');
        $ingredient = $this->makeIngredient($tenant, 'Tea');

        $stock = InventoryStock::create([
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 4,
        ]);

        $this->assertSame(6.0, $stock->available_quantity);
    }
}
