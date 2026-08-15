<?php

namespace Tests\Feature\MartPurchaseOrders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MartPurchaseOrder;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MartPurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Unlike the restaurant PurchaseOrder resource, MartPurchaseOrderController
 * was already a complete, correct, carefully-written feature (row-locking
 * for concurrent receive safety, generic status transitions via update()).
 * This migration is structural (Repository/Service/FormRequest), preserving
 * behavior verbatim — plus one defensive fix: generatePoNumber()'s
 * uniqueness check now uses withoutGlobalScopes() to match po_number's
 * actual database-wide unique constraint (same bug class fixed for the
 * restaurant PurchaseOrder, though collision odds here are negligible
 * thanks to the random suffix).
 */
class MartPurchaseOrderServiceTest extends TestCase
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

    private function setUpOrder(string $tenantSlug): array
    {
        [$tenant, $owner] = $this->makeTenantWithOwner($tenantSlug);
        Auth::login($owner);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y']);
        $supplier = Supplier::create(['tenant_id' => $tenant->id, 'name' => 'Acme Supplies']);
        $category = Category::create(['name' => 'Drinks']);
        $category->tenants()->attach($tenant->id);
        $product = Product::create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Soda Can',
            'base_price' => 1,
            'stock_quantity' => 0,
        ]);

        $service = $this->app->make(MartPurchaseOrderService::class);
        $order = $service->create([
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost' => 2],
            ],
        ]);

        return [$service, $order, $product];
    }

    public function test_create_generates_po_number_and_total(): void
    {
        [, $order] = $this->setUpOrder('TenantA');

        $this->assertStringStartsWith('MPO-', $order->po_number);
        $this->assertSame('20.00', $order->total_amount);
        $this->assertSame('draft', $order->status);
    }

    public function test_update_can_transition_status_directly(): void
    {
        [$service, $order] = $this->setUpOrder('TenantB');

        $updated = $service->update($order, ['status' => 'confirmed']);

        $this->assertSame('confirmed', $updated->status);
    }

    public function test_receive_updates_stock_and_status(): void
    {
        [$service, $order, $product] = $this->setUpOrder('TenantC');
        $service->update($order, ['status' => 'confirmed']);
        $item = $order->items->first();

        $received = $service->receive($order->refresh(), [
            ['id' => $item->id, 'quantity_received' => 10],
        ]);

        $this->assertSame('received', $received->status);
        $this->assertSame(10.0, (float) $product->refresh()->stock_quantity);
    }

    public function test_receive_clamps_to_remaining_quantity(): void
    {
        [$service, $order, $product] = $this->setUpOrder('TenantD');
        $item = $order->items->first();

        $service->receive($order, [['id' => $item->id, 'quantity_received' => 999]]);

        $this->assertSame(10.0, (float) $product->refresh()->stock_quantity);
    }

    public function test_receive_is_blocked_once_cancelled(): void
    {
        [$service, $order] = $this->setUpOrder('TenantE');
        $service->cancel($order);
        $item = $order->items->first();

        $this->expectException(ValidationException::class);
        $service->receive($order->refresh(), [['id' => $item->id, 'quantity_received' => 1]]);
    }

    public function test_delete_is_blocked_once_confirmed(): void
    {
        [$service, $order] = $this->setUpOrder('TenantF');
        $service->update($order, ['status' => 'confirmed']);

        $this->expectException(ValidationException::class);
        $service->delete($order->refresh());
    }

    public function test_update_items_replaces_line_items_and_recomputes_total(): void
    {
        [$service, $order] = $this->setUpOrder('TenantG');
        $category = Category::first();
        $product2 = Product::create([
            'tenant_id' => $order->tenant_id,
            'category_id' => $category->id,
            'name' => 'Juice Box',
            'base_price' => 1,
            'stock_quantity' => 0,
        ]);

        $updated = $service->update($order, [
            'items' => [
                ['product_id' => $product2->id, 'quantity_ordered' => 5, 'unit_cost' => 3],
            ],
        ]);

        $this->assertCount(1, $updated->items);
        $this->assertSame('Juice Box', $updated->items->first()->product_name);
        $this->assertSame('15.00', $updated->total_amount);
    }

    public function test_a_tenant_only_sees_their_own_orders(): void
    {
        $this->setUpOrder('TenantH');
        [$serviceI] = $this->setUpOrder('TenantI');

        $this->assertSame(1, $serviceI->list([])->total());
    }
}
