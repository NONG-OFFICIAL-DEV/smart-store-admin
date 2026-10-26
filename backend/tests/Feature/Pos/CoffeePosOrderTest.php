<?php

namespace Tests\Feature\Pos;

use App\Http\Controllers\Api\CoffeePOSorderController;
use App\Models\Branch;
use App\Models\BusinessType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\KitchenDisplayTicket;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Coffee POS checkout used to hard-code order_type to 'takeaway' and never
 * accepted table_id/customer_id at all — a dine-in sale at a table was
 * recorded identically to a counter takeaway, and Loyalty could never
 * accrue from a real POS sale. These tests cover the new wiring.
 */
class CoffeePosOrderTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithOwner(string $name, ?string $businessTypeCode = null): array
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
            'business_type_id' => $businessTypeCode
                ? BusinessType::firstWhere('code', $businessTypeCode)?->id
                : null,
        ]);

        return [$tenant, $owner];
    }

    private function makeBranch(Tenant $tenant): Branch
    {
        return Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
            'address_line1' => '123 Main St',
            'city' => 'Phnom Penh',
        ]);
    }

    private function makeProduct(string $tenantId): \App\Models\Product
    {
        $category = Category::create(['name' => 'Drinks']);

        return $this->app->make(ProductService::class)->create([
            'category_id' => $category->id,
            'name' => 'Iced Latte',
            'base_price' => 4.5,
        ], $tenantId);
    }

    private function submit(array $overrides = []): \Illuminate\Http\JsonResponse
    {
        $request = Request::create('/coffee/pos/orders', 'POST', array_merge([
            'payment_method' => 'cash',
        ], $overrides));

        return $this->app->make(CoffeePOSorderController::class)->coffeeOrders($request);
    }

    public function test_dine_in_order_persists_order_type_table_and_customer_and_occupies_the_table(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Sok',
            'last_name' => 'Dara',
        ]);

        $response = $this->submit([
            'branch_id' => $branch->id,
            'order_type' => 'dine_in',
            'table_id' => $table->id,
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $data = $response->getData(true)['data'];
        $this->assertSame('dine_in', $data['order_type']);
        $this->assertSame('A1', $data['table_number']);
        $this->assertSame('Sok Dara', $data['customer_name']);

        $order = Order::firstWhere('order_number', $data['order_number']);
        $this->assertSame('dine_in', $order->order_type);
        $this->assertSame($table->id, $order->table_id);
        $this->assertSame($customer->id, $order->customer_id);
        // Occupied even though this order was paid in full (default
        // payment_method: cash) at checkout — table occupancy tracks
        // whether staff has freed the table, not payment status, since a
        // paid customer can still be sitting there.
        $this->assertSame(Table::STATUS_OCCUPIED, $table->fresh()->status);
    }

    public function test_order_type_defaults_to_takeaway_when_omitted_matching_prior_behavior(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);

        $response = $this->submit([
            'branch_id' => $branch->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $data = $response->getData(true)['data'];
        $this->assertSame('takeaway', $data['order_type']);
        $this->assertNull($data['table_number']);
        $this->assertNull($data['customer_name']);
    }

    public function test_a_table_belonging_to_another_tenant_is_rejected(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');
        Auth::login($ownerA);
        $branchA = $this->makeBranch($tenantA);
        $branchB = $this->makeBranch($tenantB);
        $product = $this->makeProduct($tenantA->id);
        $foreignTable = Table::create(['branch_id' => $branchB->id, 'table_number' => 'B1']);

        $this->expectException(HttpResponseException::class);

        $this->submit([
            'branch_id' => $branchA->id,
            'order_type' => 'dine_in',
            'table_id' => $foreignTable->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
    }

    public function test_selected_modifier_options_add_their_price_adjustment_to_the_unit_price(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);

        $group = ModifierGroup::create([
            'tenant_id' => $tenant->id,
            'name' => 'Milk',
            'selection_type' => 'single',
            'is_required' => true,
        ]);
        $oatMilk = ModifierOption::create([
            'group_id' => $group->id,
            'name' => 'Oat Milk',
            'price_adjustment' => 0.5,
        ]);
        $extraShot = ModifierOption::create([
            'group_id' => $group->id,
            'name' => 'Extra Shot',
            'price_adjustment' => 0.75,
        ]);

        $response = $this->submit([
            'branch_id' => $branch->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'modifier_option_ids' => [$oatMilk->id, $extraShot->id],
                ],
            ],
        ]);

        $data = $response->getData(true)['data'];
        // base_price 4.5 + 0.5 + 0.75 = 5.75 per unit, x2 quantity = 11.5
        $this->assertSame(11.5, $data['subtotal']);

        // Coffee POS used to discard modifier selections entirely after the
        // print response ("no modifiers table for coffee POS") — the
        // kitchen display, bill, and any later reprint had nothing to show.
        $order = Order::findOrFail($data['id']);
        $modifierNames = $order->items->first()->modifiers->pluck('option_name')->sort()->values()->all();
        $this->assertSame(['Extra Shot', 'Oat Milk'], $modifierNames);
    }

    public function test_a_custom_modifier_value_persists_with_no_price_of_its_own(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);

        $group = ModifierGroup::create([
            'tenant_id' => $tenant->id,
            'name' => 'Sugar',
            'selection_type' => 'single',
            'allow_custom_value' => true,
            'custom_suffix' => '%',
        ]);

        $response = $this->submit([
            'branch_id' => $branch->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'custom_modifier_values' => [
                        ['group_id' => $group->id, 'value' => '80'],
                    ],
                ],
            ],
        ]);

        $data = $response->getData(true)['data'];
        // The custom input has no price of its own — only the base price.
        $this->assertSame(4.5, $data['subtotal']);

        $order = Order::findOrFail($data['id']);
        $modifier = $order->items->first()->modifiers->first();
        $this->assertNull($modifier->modifier_option_id);
        $this->assertSame('80%', $modifier->custom_value);
        $this->assertSame('Sugar', $modifier->option_name);
        $this->assertSame(0.0, (float) $modifier->price_adjustment);
    }

    public function test_a_modifier_option_belonging_to_another_tenant_contributes_no_price(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantWithOwner('TenantA');
        [$tenantB] = $this->makeTenantWithOwner('TenantB');
        Auth::login($ownerA);
        $branchA = $this->makeBranch($tenantA);
        $product = $this->makeProduct($tenantA->id);

        $foreignGroup = ModifierGroup::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Milk',
            'selection_type' => 'single',
        ]);
        $foreignOption = ModifierOption::create([
            'group_id' => $foreignGroup->id,
            'name' => 'Oat Milk',
            'price_adjustment' => 0.5,
        ]);

        $response = $this->submit([
            'branch_id' => $branchA->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'modifier_option_ids' => [$foreignOption->id],
                ],
            ],
        ]);

        $data = $response->getData(true)['data'];
        $this->assertSame(4.5, $data['subtotal']);
    }

    public function test_pay_later_creates_a_pending_order_with_no_payment_and_still_occupies_the_table(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);
        $table = Table::create(['branch_id' => $branch->id, 'table_number' => 'A1']);

        $response = $this->submit([
            'branch_id' => $branch->id,
            'payment_method' => 'pay_later',
            'order_type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $data = $response->getData(true)['data'];
        $this->assertSame('pay_later', $data['payment_method']);
        $this->assertNull($data['payment_id']);
        $this->assertNull($data['cash_tendered']);

        $order = Order::firstWhere('order_number', $data['order_number']);
        $this->assertSame('pending', $order->status);
        $this->assertNull($order->completed_at);
        $this->assertCount(0, $order->payments);
        $this->assertSame(Table::STATUS_OCCUPIED, $table->fresh()->status);
    }

    public function test_pay_later_receipt_has_no_payment_amount_and_flags_payment_method_as_pay_later(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantA');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);

        $response = $this->submit([
            'branch_id' => $branch->id,
            'payment_method' => 'pay_later',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $receipt = $response->getData(true)['data']['prints']['receipt'];
        $this->assertSame('pay_later', $receipt['payment_method']);
        $this->assertNull($receipt['cash_tendered']);
        $this->assertNull($receipt['change_given']);
    }

    public function test_a_food_tenant_pos_sale_auto_creates_a_kitchen_ticket(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantFood', 'RESTAURANT');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);

        $response = $this->submit([
            'branch_id' => $branch->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $orderId = $response->getData(true)['data']['id'];

        $this->assertSame(1, KitchenDisplayTicket::where('order_id', $orderId)->count());
    }

    public function test_a_non_food_tenant_pos_sale_does_not_create_a_kitchen_ticket(): void
    {
        [$tenant, $owner] = $this->makeTenantWithOwner('TenantMart', 'MART');
        Auth::login($owner);
        $branch = $this->makeBranch($tenant);
        $product = $this->makeProduct($tenant->id);

        $response = $this->submit([
            'branch_id' => $branch->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $orderId = $response->getData(true)['data']['id'];

        $this->assertSame(0, KitchenDisplayTicket::where('order_id', $orderId)->count());
    }
}
