<?php

namespace Tests\Feature\Dashboard;

use App\Http\Controllers\Api\DashboardController;
use App\Models\Branch;
use App\Models\BusinessType;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * `payment_breakdown` was added to stats() alongside the redesign's Dashboard
 * "Payment Methods" card — everything else on this endpoint was already
 * working and is left untouched.
 */
class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_reports_payment_breakdown(): void
    {
        $owner = User::create([
            'email' => 'owner@example.test', 'first_name' => 'Own', 'last_name' => 'Er',
        ]);
        $businessType = BusinessType::create(['code' => 'mart', 'name' => 'Mart']);
        $tenant = Tenant::create([
            'name' => 'T', 'slug' => 't-1', 'owner_user_id' => $owner->id,
            'business_type_id' => $businessType->id,
        ]);
        $branch = Branch::create([
            'tenant_id' => $tenant->id, 'name' => 'B', 'address_line1' => 'x', 'city' => 'y',
        ]);
        $category = Category::create(['name' => 'General']);
        $product = Product::create([
            'tenant_id' => $tenant->id, 'category_id' => $category->id, 'name' => 'Widget',
        ]);

        $order = Order::create([
            'branch_id' => $branch->id,
            'status' => 'completed',
            'total_amount' => 30,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Widget',
            'quantity' => 3,
            'unit_price' => 10,
            'total_price' => 30,
        ]);
        Payment::create([
            'order_id' => $order->id,
            'branch_id' => $branch->id,
            'payment_method' => 'cash',
            'amount' => 30,
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        Auth::login($owner);

        $response = $this->app->make(DashboardController::class)->stats(
            Request::create('/dashboard/stats', 'GET', ['period' => 'month'])
        );
        $body = json_decode($response->getContent(), true);

        $this->assertNull(collect($body['data']['kpis'])->firstWhere('label', 'Items Sold'));

        $this->assertEquals(
            [['method' => 'cash', 'amount' => 30.0, 'count' => 1]],
            $body['data']['payment_breakdown']
        );
    }
}
