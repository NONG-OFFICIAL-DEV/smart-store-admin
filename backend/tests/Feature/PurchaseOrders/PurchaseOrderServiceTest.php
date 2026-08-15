<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\InventoryStock;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * PurchaseOrderController::receive() previously did
 * $ingredient->increment('stock_quantity', ...) — a column that doesn't
 * exist on `ingredients` at all, so every "receive" click fatally errored.
 * The correct logic (InventoryStockService::adjust()) already existed on
 * the dead PurchaseOrder::receive() model method but had zero callers.
 * Also: routes referenced submit/confirm actions that never existed
 * anywhere — without them a PO could never leave 'draft', which also
 * meant `receive` was unreachable from the UI (its button only shows for
 * confirmed/partially_received status). Both gaps fixed together here.
 */
class PurchaseOrderServiceTest extends TestCase
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
        $ingredient = Ingredient::create(['tenant_id' => $tenant->id, 'name' => 'Flour', 'unit' => 'kg']);

        $service = $this->app->make(PurchaseOrderService::class);
        $order = $service->create([
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'items' => [
                ['ingredient_id' => $ingredient->id, 'quantity_ordered' => 10, 'unit_price' => 2],
            ],
        ], $tenant->id);

        return [$service, $order, $ingredient, $branch];
    }

    public function test_create_generates_po_number_and_computes_total(): void
    {
        [, $order] = $this->setUpOrder('TenantA');

        $this->assertStringStartsWith('PO-', $order->po_number);
        $this->assertSame('20.00', $order->total_amount);
        $this->assertSame('draft', $order->status);
    }

    public function test_receive_is_rejected_before_the_po_is_confirmed(): void
    {
        [$service, $order] = $this->setUpOrder('TenantB');
        $item = $order->items->first();

        $this->expectException(ValidationException::class);
        $service->receive($order, [['id' => $item->id, 'quantity_received' => 5]]);
    }

    public function test_full_workflow_submit_confirm_receive_adjusts_stock_and_ledgers_it(): void
    {
        [$service, $order, $ingredient, $branch] = $this->setUpOrder('TenantC');
        $item = $order->items->first();

        $service->submit($order);
        $service->confirm($order->refresh());

        $received = $service->receive($order->refresh(), [
            ['id' => $item->id, 'quantity_received' => 4],
        ]);

        $this->assertSame('partially_received', $received->status);
        $stock = InventoryStock::where('branch_id', $branch->id)->where('ingredient_id', $ingredient->id)->first();
        $this->assertSame('4.0000', $stock->quantity_on_hand);
    }

    public function test_receiving_the_full_remaining_quantity_marks_the_po_received(): void
    {
        [$service, $order] = $this->setUpOrder('TenantD');
        $item = $order->items->first();

        $service->submit($order);
        $service->confirm($order->refresh());
        $received = $service->receive($order->refresh(), [
            ['id' => $item->id, 'quantity_received' => 10],
        ]);

        $this->assertSame('received', $received->status);
    }

    public function test_receive_clamps_to_remaining_even_if_more_is_requested(): void
    {
        [$service, $order, $ingredient, $branch] = $this->setUpOrder('TenantE');
        $item = $order->items->first();

        $service->submit($order);
        $service->confirm($order->refresh());
        $service->receive($order->refresh(), [['id' => $item->id, 'quantity_received' => 999]]);

        $stock = InventoryStock::where('branch_id', $branch->id)->where('ingredient_id', $ingredient->id)->first();
        $this->assertSame('10.0000', $stock->quantity_on_hand);
    }

    public function test_cannot_submit_a_non_draft_po(): void
    {
        [$service, $order] = $this->setUpOrder('TenantF');
        $service->submit($order);

        $this->expectException(ValidationException::class);
        $service->submit($order->refresh());
    }

    public function test_delete_is_blocked_once_a_po_is_submitted(): void
    {
        [$service, $order] = $this->setUpOrder('TenantG');
        $service->submit($order);

        $this->expectException(ValidationException::class);
        $service->delete($order->refresh());
    }

    public function test_cancel_is_blocked_once_received(): void
    {
        [$service, $order] = $this->setUpOrder('TenantH');
        $item = $order->items->first();
        $service->submit($order);
        $service->confirm($order->refresh());
        $service->receive($order->refresh(), [['id' => $item->id, 'quantity_received' => 10]]);

        $this->expectException(ValidationException::class);
        $service->cancel($order->refresh());
    }

    public function test_a_tenant_only_sees_their_own_orders(): void
    {
        $this->setUpOrder('TenantI');
        [$serviceJ] = $this->setUpOrder('TenantJ');

        // TenantJ's owner is currently logged in (setUpOrder logs in as the
        // new owner each time) — list() with no tenant filter still goes
        // through TenantScope, so only TenantJ's own order should show.
        $this->assertSame(1, $serviceJ->list([])->total());
    }
}
