<?php

namespace Tests\Feature\Reports;

use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\InventoryStock;
use App\Models\InventoryTransaction;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InventoryReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Ingredient-based inventory report (InventoryStock/InventoryTransaction) —
 * deliberately separate from Mart's own product/stock-movement report.
 */
class InventoryReportTest extends TestCase
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

    public function test_low_stock_and_out_of_stock_ingredients_are_classified_correctly(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);

        $lowStockIngredient = Ingredient::create([
            'tenant_id' => $tenant->id, 'name' => 'Flour', 'unit' => 'kg', 'reorder_point' => 10,
        ]);
        InventoryStock::create(['branch_id' => $branch->id, 'ingredient_id' => $lowStockIngredient->id, 'quantity_on_hand' => 5]);

        $outOfStockIngredient = Ingredient::create([
            'tenant_id' => $tenant->id, 'name' => 'Sugar', 'unit' => 'kg', 'reorder_point' => 10,
        ]);
        InventoryStock::create(['branch_id' => $branch->id, 'ingredient_id' => $outOfStockIngredient->id, 'quantity_on_hand' => 0]);

        $healthyIngredient = Ingredient::create([
            'tenant_id' => $tenant->id, 'name' => 'Salt', 'unit' => 'kg', 'reorder_point' => 10,
        ]);
        InventoryStock::create(['branch_id' => $branch->id, 'ingredient_id' => $healthyIngredient->id, 'quantity_on_hand' => 50]);

        $result = $this->app->make(InventoryReportService::class)->report([]);

        $this->assertSame(3, $result['summary']['total_ingredients']);
        $this->assertSame(2, $result['summary']['low_stock_count']);
        $this->assertSame(1, $result['summary']['out_of_stock_count']);
        $this->assertTrue($result['low_stock']->contains(fn ($s) => $s->ingredient_id === $lowStockIngredient->id));
        $this->assertTrue($result['low_stock']->contains(fn ($s) => $s->ingredient_id === $outOfStockIngredient->id));
    }

    public function test_movement_summary_sums_by_transaction_type_within_the_date_range(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantB');
        Auth::login($owner);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        $ingredient = Ingredient::create(['tenant_id' => $tenant->id, 'name' => 'Flour', 'unit' => 'kg']);

        InventoryTransaction::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'transaction_type' => 'purchase', 'quantity' => 20])
            ->forceFill(['created_at' => '2026-03-10 10:00:00'])->save();
        InventoryTransaction::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'transaction_type' => 'purchase', 'quantity' => 10])
            ->forceFill(['created_at' => '2026-03-12 10:00:00'])->save();
        InventoryTransaction::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'transaction_type' => 'usage', 'quantity' => 5])
            ->forceFill(['created_at' => '2026-03-11 10:00:00'])->save();
        // Out of range — should not be counted.
        InventoryTransaction::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'transaction_type' => 'purchase', 'quantity' => 999])
            ->forceFill(['created_at' => '2026-01-01 10:00:00'])->save();

        $result = $this->app->make(InventoryReportService::class)->report([
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]);

        $this->assertSame(30.0, $result['movement_summary']['purchase']);
        $this->assertSame(5.0, $result['movement_summary']['usage']);
    }

    public function test_branch_filter_narrows_stock_and_movements_to_that_branch_only(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantC');
        Auth::login($owner);
        $branchA = Branch::create(['tenant_id' => $tenant->id, 'name' => 'A', 'address_line1' => 'x', 'city' => 'y']);
        $branchB = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        $ingredient = Ingredient::create(['tenant_id' => $tenant->id, 'name' => 'Flour', 'unit' => 'kg']);

        InventoryStock::create(['branch_id' => $branchA->id, 'ingredient_id' => $ingredient->id, 'quantity_on_hand' => 5]);
        InventoryStock::create(['branch_id' => $branchB->id, 'ingredient_id' => $ingredient->id, 'quantity_on_hand' => 8]);

        $result = $this->app->make(InventoryReportService::class)->report(['branch_id' => $branchA->id]);

        $this->assertSame(1, $result['summary']['total_ingredients']);
        $this->assertSame($branchA->id, $result['stock'][0]->branch_id);
    }
}
