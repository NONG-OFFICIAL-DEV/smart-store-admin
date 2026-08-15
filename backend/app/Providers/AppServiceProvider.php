<?php

namespace App\Providers;

use App\Observers\ActivityLogObserver;
use App\Observers\NotificationObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Auth\Notifications\ResetPassword;
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

        // ── Auth rate limiting ───────────────────────────────────────────────
        // Keyed by both IP and email so neither a distributed spray (many
        // emails, one IP) nor a targeted brute-force (one email, many IPs
        // via botnet/proxy rotation) slips through on just one key.
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email', ''));

            return [
                Limit::perMinute(5)->by('login-ip:' . $request->ip()),
                Limit::perMinute(5)->by('login-email:' . $email),
            ];
        });

        RateLimiter::for('refresh', function (Request $request) {
            return Limit::perMinute(30)->by('refresh-ip:' . $request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            $email = Str::lower((string) $request->input('email', ''));

            return [
                Limit::perMinute(3)->by('pw-reset-ip:' . $request->ip()),
                Limit::perMinute(3)->by('pw-reset-email:' . $email),
            ];
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(5)->by('register-ip:' . $request->ip());
        });

        // Own bucket, deliberately separate from 'login' — a 2FA code is a
        // real user fat-fingering 6 digits, a different retry shape than a
        // password attempt. Keyed by the challenge token itself (already a
        // single-use, per-attempt-session identifier).
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by('2fa:' . $request->input('two_factor_token'));
        });

        // SPA password reset — the emailed link must point at the frontend's
        // own reset-password page, not a backend route that doesn't render
        // anything, matching frontend_url's use for the notification emails.
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $frontendUrl = rtrim(config('app.frontend_url'), '/');
            $email = urlencode($notifiable->getEmailForPasswordReset());

            return "{$frontendUrl}/reset-password?token={$token}&email={$email}";
        });
    }
}
