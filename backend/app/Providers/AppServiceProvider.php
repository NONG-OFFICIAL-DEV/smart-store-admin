<?php

namespace App\Providers;

use App\Observers\ActivityLogObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;
use App\Models\{Product, Category, Branch, BranchMenu, MartPurchaseOrder, Tenant, Staff, Menu, Table, Order, OrderItem, Payment, ProductVariant, Shift, Supplier};
use App\Models\Scopes\TenantScope;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ── Only track these models ────────────────────────────────────────
        Product::observe(ActivityLogObserver::class);
        Category::observe(ActivityLogObserver::class);
        Branch::observe(ActivityLogObserver::class);
        Tenant::observe(ActivityLogObserver::class);
        Staff::observe(ActivityLogObserver::class);
        Menu::observe(ActivityLogObserver::class);
        BranchMenu::observe(ActivityLogObserver::class);
        Table::observe(ActivityLogObserver::class);
        Order::observe(ActivityLogObserver::class);
        OrderItem::observe(ActivityLogObserver::class);
        Payment::observe(ActivityLogObserver::class);
        Supplier::observe(ActivityLogObserver::class);
        ProductVariant::observe(ActivityLogObserver::class);
        Shift::observe(ActivityLogObserver::class);
        Broadcast::routes();
    // for get data by specific user
        $tenantModels = [
            Branch::class,
            Product::class,
            ProductVariant::class,
            Category::class,
            Menu::class,
            BranchMenu::class,
            Staff::class,
            Table::class,
            Shift::class,
            Supplier::class,
            MartPurchaseOrder::class
        ];

        foreach ($tenantModels as $model) {
            $model::addGlobalScope(new TenantScope());
        }
    }
}
