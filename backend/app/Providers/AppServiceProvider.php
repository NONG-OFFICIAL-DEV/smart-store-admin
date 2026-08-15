<?php

namespace App\Providers;

use App\Observers\ActivityLogObserver;
use App\Observers\NotificationObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;
use App\Models\{Product, Category, Branch, BranchMenu, MartPurchaseOrder, Tenant, Staff, Menu, Table, Order, OrderItem, Payment, ProductVariant, PurchaseOrder, Shift, Supplier, Role, Notification};
use App\Models\Scopes\TenantScope;
use App\Services\TenantResolver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->singleton(TenantResolver::class);
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
        Notification::observe(NotificationObserver::class);

        // Registered under 'api' prefix + jwt.auth (not the default `web`
        // middleware Broadcast::routes() uses with no args) — this app has
        // no sessions/cookies, only JWT, and the frontend's axios baseURL
        // already includes /api, so this lands at /api/broadcasting/auth.
        Broadcast::routes(['prefix' => 'api', 'middleware' => ['jwt.auth']]);
        require base_path('routes/channels.php');

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
            MartPurchaseOrder::class,
            PurchaseOrder::class,
            Role::class,
        ];
    // for using ouside GlobalScope
        foreach ($tenantModels as $model) {
            $model::addGlobalScope(new TenantScope());
        }
    }
}
