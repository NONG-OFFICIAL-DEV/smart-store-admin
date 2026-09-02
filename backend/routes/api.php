<?php

use Illuminate\Support\Facades\Route;
// ── Auth ───────────────────────────────────────────────────────────────────
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\TwoFactorAuthController;
use App\Http\Controllers\Api\V1\UserController;

// ── Multi-Tenancy ──────────────────────────────────────────────────────────
use App\Http\Controllers\Api\AdminTenantUserController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\BranchHourController;

// ── Users & Roles ──────────────────────────────────────────────────────────
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\ShiftController;

// ── Menu & Products ────────────────────────────────────────────────────────
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\ModifierGroupController;
use App\Http\Controllers\Api\ModifierOptionController;
use App\Http\Controllers\Api\BranchProductOverrideController;

// ── Tables & Floor ─────────────────────────────────────────────────────────
use App\Http\Controllers\Api\FloorPlanController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\ReservationController;

// ── Orders ─────────────────────────────────────────────────────────────────
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderItemController;
use App\Http\Controllers\Api\KitchenDisplayTicketController;

// ── Payments ───────────────────────────────────────────────────────────────
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\CashDrawerController;

// ── Inventory ──────────────────────────────────────────────────────────────
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\IngredientController;
use App\Http\Controllers\Api\InventoryStockController;
use App\Http\Controllers\Api\InventoryTransactionController;
use App\Http\Controllers\Api\ProductRecipeController;
use App\Http\Controllers\Api\PurchaseOrderController;

// ── Customers ──────────────────────────────────────────────────────────────
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerAddressController;
use App\Http\Controllers\Api\LoyaltyTransactionController;

// ── Promotions ─────────────────────────────────────────────────────────────
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\CouponController;

// ── Reporting ──────────────────────────────────────────────────────────────
use App\Http\Controllers\Api\DailySalesSummaryController;
use App\Http\Controllers\Api\InventoryReportController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\BranchMenuController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\DigitalMenuController;
use App\Http\Controllers\Api\V1\ShiftAssignmentController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\BusinessTypeController;
use App\Http\Controllers\Api\CoffeePOSorderController;
use App\Http\Controllers\Api\HospitalityPosController;
use App\Http\Controllers\Api\MartPosController;
use App\Http\Controllers\Api\MartPurchaseOrderController;
use App\Http\Controllers\Api\OrderExportController;
use App\Http\Controllers\Api\ProductUnitController;
use App\Http\Controllers\Api\StockAdjustmentController;
use App\Http\Controllers\Api\MartProductController;
use App\Http\Controllers\Api\MartProductPerformanceController;
use App\Http\Controllers\Api\MartPurchaseReportController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\PlanFeatureListingController;
use App\Http\Controllers\Api\TenantSubscriptionController;
use App\Http\Controllers\Api\SubscriptionPlanHistoryController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\TelegramSettingController;

Route::get('/test', [AuthController::class, 'test']);

// ── Public routes (no auth needed) ──────────────────────────────────────────
Route::post('/login',     [AuthController::class, 'login'])->middleware('throttle:login');

// Token refresh — intentionally outside 'jwt.auth': this endpoint never
// looks at the (possibly long-expired) access token at all, only the
// refresh token in the body, so there's no JWT for that middleware to
// validate in the first place. See AuthController::refresh().
Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:refresh');

// Second step of a 2FA-gated login — no JWT exists yet at this point
// either (see AuthController::login()), so this also stays outside jwt.auth.
Route::post('/two-factor/verify', [AuthController::class, 'verifyTwoFactor'])->middleware('throttle:two-factor');

Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset');

// ── Protected routes ─────────────────────────────────────────────────────────
Route::middleware(['jwt.auth', 'password.changed'])->group(function () {
    Route::get('/me',      [AuthController::class, 'me']);
    Route::post('/logout',  [AuthController::class, 'logout']);
    Route::put('/set-pin', [AuthController::class, 'setPin']);

    Route::prefix('two-factor')->group(function () {
        Route::post('setup',   [TwoFactorAuthController::class, 'setup']);
        Route::post('confirm', [TwoFactorAuthController::class, 'confirm']);
        Route::delete('/',     [TwoFactorAuthController::class, 'disable']);
    });

    Route::prefix('users')->middleware('superadmin')->group(function () {
        Route::get('/',      [UserController::class, 'index']);
        Route::post('/',     [UserController::class, 'store']);
        Route::get('/{user}',  [UserController::class, 'show']);
        Route::put('/{user}',  [UserController::class, 'update']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
        Route::post('/{user}/reset-password', [UserController::class, 'resetPassword']);
    });
});

// =============================================================================
// PROTECTED ROUTES (auth required)
// =============================================================================
Route::prefix('v1')->middleware(['jwt.auth', 'password.changed', 'subscription.active'])->group(function () {

    // ── Auth (protected) ──────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        // Route::post('logout',  [AuthController::class, 'logout']);
        // Route::get('me',       [AuthController::class, 'me']);
        Route::put('profile',  [AuthController::class, 'updateProfile']);
        Route::put('password', [AuthController::class, 'changePassword']);
        Route::put('email',    [AuthController::class, 'updateEmail']);
    });

    // Tenant self-service read — a tenant owner/staff must be able to view
    // their OWN tenant's profile (see TenantProfile.vue). Kept outside the
    // 'superadmin' group below, but TenantController::show() itself still
    // enforces that non-super-admins may only fetch their own tenant.
    Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->withoutMiddleware('subscription.active');

    // Tenant self-service company-info update (Settings > Company Info tab)
    // — owner-only, enforced inside the controller (not superadmin-gated,
    // deliberately narrower field set than the admin update() below).
    Route::put('/tenants/{tenant}/profile', [TenantController::class, 'updateProfile']);

    // ── System / Super-admin only ──────────────────────────────────────────────
    Route::middleware('superadmin')->group(function () {
        Route::apiResource('tenants', TenantController::class)->except(['show']);
        Route::get('/tenants/{tenant}/edit', [TenantController::class, 'edit']);
        Route::post('tenants/{tenant}/toggle-active', [TenantController::class, 'toggleActive']);
        Route::post('tenants/{tenant}/reset-owner-password', [TenantController::class, 'resetOwnerPassword']);
        Route::post('tenants/{tenant}/transfer-ownership', [TenantController::class, 'transferOwnership']);

        Route::prefix('tenants/{tenant}/users')->group(function () {
            Route::get('/', [AdminTenantUserController::class, 'index']);
            Route::post('/impersonate', [AdminTenantUserController::class, 'impersonate']);
            Route::post('/{user}/deactivate', [AdminTenantUserController::class, 'deactivate']);
            Route::post('/{user}/reactivate', [AdminTenantUserController::class, 'reactivate']);
            Route::post('/{user}/reset-password', [AdminTenantUserController::class, 'resetPassword']);
        });

        Route::apiResource('business-types', BusinessTypeController::class)
            ->except(['index', 'show']);

        Route::apiResource('plans', PlanController::class);
        Route::patch('plans/{plan}/toggle-active', [PlanController::class, 'toggleActive']);
        Route::apiResource('plan-feature-listings', PlanFeatureListingController::class)->except(['show']);

        // Subscriptions — assigning/changing a tenant's plan (store) and its
        // lifecycle (renew/cancel/toggle/destroy). No generic "update" route:
        // a plan change always goes through store(), never an in-place edit.
        Route::apiResource('subscriptions', TenantSubscriptionController::class)
            ->only(['index', 'show', 'store', 'destroy']);
        Route::patch('subscriptions/{subscription}/toggle-active', [TenantSubscriptionController::class, 'toggleActive']);
        Route::patch('subscriptions/{subscription}/cancel', [TenantSubscriptionController::class, 'cancel']);
        Route::patch('subscriptions/{subscription}/renew', [TenantSubscriptionController::class, 'renew']);

        Route::get('subscription-plan-history', [SubscriptionPlanHistoryController::class, 'index']);

        // Manual payment reconciliation ledger — records payments already
        // received (bank transfer, cash) against a tenant's subscription.
        Route::post('tenants/{tenant}/payments', [TenantSubscriptionController::class, 'recordPayment']);
        Route::get('tenants/{tenant}/payments', [TenantSubscriptionController::class, 'payments']);

        Route::prefix('admin/dashboard')->group(function () {
            Route::get('stats',        [AdminDashboardController::class, 'stats']);
            Route::get('chart',        [AdminDashboardController::class, 'chart']);
            Route::get('tenant-chart', [AdminDashboardController::class, 'tenantChart']);
        });

        Route::prefix('telegram-settings')->group(function () {
            Route::get('/',    [TelegramSettingController::class, 'show']);
            Route::put('/',    [TelegramSettingController::class, 'update']);
            Route::post('test', [TelegramSettingController::class, 'test']);
        });
    });

    // Tenant's own billing read — stays open to any authenticated tenant user
    // (the controller itself enforces tenant isolation). Exempt from
    // subscription.active too — a suspended tenant must still be able to
    // see their own billing status to know what to do about it.
    Route::get('plans/{tenant}/billing', [TenantController::class, 'getSubscriptionByTenant'])->withoutMiddleware('subscription.active');

    // ── Self-service billing (Tenant Owner) ─────────────────────────────────────
    // Always resolves the CALLER'S OWN tenant — never accepts a client-supplied
    // tenant_id. Gated by permission:billing.manage, which the Owner already
    // bypasses automatically (see CheckPermission), leaving room to later
    // delegate billing to a trusted Manager.
    Route::middleware('permission:billing.manage')->prefix('billing')->group(function () {
        Route::get('plans', [PlanController::class, 'publicPlans']);
        Route::post('change-plan', [BillingController::class, 'changePlan']);
        Route::post('renew', [BillingController::class, 'renew']);
    });

    // Business types & their branch types — reference/catalog data, not
    // tenant-scoped, so any authenticated user (tenant owner/staff creating
    // a branch) can read them; only mutation stays superadmin-only above.
    Route::get('/business-types', [BusinessTypeController::class, 'index']);
    Route::get('/business-types/{business_type}', [BusinessTypeController::class, 'show']);
    Route::get('/business-types/{business_type}/branch-types', [BusinessTypeController::class, 'branchTypes']);

    // ── Branches ──────────────────────────────────────────────────────────────
    // Pre-existing finer-grained codes (view/delete) honored here instead of
    // the single .manage convention used for the other modules below.
    Route::apiResource('branches', BranchController::class)
        ->middlewareFor(['index', 'show'], 'permission:branches.view')
        ->middlewareFor(['store', 'update'], 'permission:branches.manage')
        ->middlewareFor('destroy', 'permission:branches.delete');
    Route::post('branches/{branch}/toggle-open', [BranchController::class, 'toggleOpen'])
        ->middleware('permission:branches.manage');
    Route::prefix('branches/{branch}')->group(function () {
        Route::get('hours',          [BranchHourController::class, 'index']);
        Route::post('hours',         [BranchHourController::class, 'store'])->middleware('permission:branches.manage');
        Route::put('hours/{hour}',   [BranchHourController::class, 'update'])->middleware('permission:branches.manage');
        Route::delete('hours/{hour}', [BranchHourController::class, 'destroy'])->middleware('permission:branches.manage');

        Route::get('staff',          [StaffController::class, 'byBranch']);
        Route::get('orders',         [OrderController::class, 'byBranch']);
        Route::get('tables',         [TableController::class, 'byBranch']);
        Route::get('floor-plans',    [FloorPlanController::class, 'byBranch']);
        Route::get('inventory',      [InventoryStockController::class, 'byBranch']);
        Route::get('reservations',   [ReservationController::class, 'byBranch']);
        Route::get('sales-summary',  [DailySalesSummaryController::class, 'byBranch']);
        Route::post('sales-summary/generate', [DailySalesSummaryController::class, 'generate']);
    });

    // ── Roles & Permissions ───────────────────────────────────────────────────
    // Creating/editing/deleting a role and syncing its permissions are now
    // reachable by a tenant Owner (or any staff explicitly granted
    // roles.manage) — RoleService already fully guards this: is_system is
    // never client-settable (RoleService::create()), the protected Owner
    // role can't be touched by anyone (assertNotSystem()), and tenant_id is
    // always server-resolved, never client-supplied. The permission catalog
    // itself (the fixed list of codes tenants pick from) stays
    // super-admin-only — it's shared system-wide reference data, not
    // tenant-owned business data.
    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('roles',       [RoleController::class, 'index']);
        Route::get('roles/{role}', [RoleController::class, 'show']);
        Route::post('roles', [RoleController::class, 'store']);
        Route::put('roles/{role}', [RoleController::class, 'update']);
        Route::patch('roles/{role}', [RoleController::class, 'update']);
        Route::delete('roles/{role}', [RoleController::class, 'destroy']);
        Route::post('roles/{role}/permissions/sync', [RoleController::class, 'syncPermissions']);
        // Read-only catalog list — a tenant needs this to render which
        // permission codes exist when building a role's checkbox list.
        Route::get('permissions', [PermissionController::class, 'index']);
        Route::get('permissions/{permission}', [PermissionController::class, 'show']);
    });
    Route::middleware(['permission:roles.manage', 'superadmin'])->group(function () {
        Route::post('permissions', [PermissionController::class, 'store']);
        Route::put('permissions/{permission}', [PermissionController::class, 'update']);
        Route::patch('permissions/{permission}', [PermissionController::class, 'update']);
        Route::delete('permissions/{permission}', [PermissionController::class, 'destroy']);
    });

    // ── Staff ─────────────────────────────────────────────────────────────────
    Route::middleware('permission:staff.manage')->group(function () {
        Route::apiResource('staff', StaffController::class);
        Route::post('staff/{staff}/reset-password', [StaffController::class, 'resetPassword']);
        Route::prefix('staff/{staff}')->group(function () {
            Route::get('shifts',       [ShiftController::class, 'byStaff']);
            Route::post('clock-in',    [ShiftController::class, 'clockIn']);
            Route::post('clock-out',   [ShiftController::class, 'clockOut']);
        });
    });

    // ── Shifts (definitions + assignments) ─────────────────────────────────────
    Route::middleware('permission:shifts.manage')->group(function () {
        Route::apiResource('shifts', ShiftController::class);
        Route::apiResource('shift-assignments', ShiftAssignmentController::class);
        Route::post('shift-assignments/{shift_assignment}/clock-in',  [ShiftAssignmentController::class, 'clockIn']);
        Route::post('shift-assignments/{shift_assignment}/clock-out', [ShiftAssignmentController::class, 'clockOut']);
    });

    // ── Menus ─────────────────────────────────────────────────────────────────
    Route::middleware('permission:menus.manage')->group(function () {
        Route::apiResource('menus', MenuController::class);
        Route::prefix('menus/{menu}')->group(function () {
            Route::get('categories',          [CategoryController::class, 'byMenu']);
            Route::post('branches/sync',      [MenuController::class, 'syncBranches']);
        });
    });

    // ── Categories ────────────────────────────────────────────────────────────
    Route::apiResource('categories', CategoryController::class)->middleware('permission:categories.manage');
    Route::prefix('categories/{category}')->group(function () {
        Route::get('products', [ProductController::class, 'byCategory']);
    });

    // ── Products ──────────────────────────────────────────────────────────────
    Route::middleware('permission:products.manage')->group(function () {
        Route::apiResource('products', ProductController::class);
        Route::apiResource('product-variants', ProductVariantController::class);
        Route::prefix('products/{product}')->group(function () {
            Route::apiResource('variants',         ProductVariantController::class)->shallow();
            Route::post('modifier-groups/sync',    [ProductController::class, 'attachModifierGroups']);
            Route::post('recipe',                  [ProductRecipeController::class, 'store']);
            Route::put('recipe/{recipe}',          [ProductRecipeController::class, 'update']);
            Route::delete('recipe/{recipe}',       [ProductRecipeController::class, 'destroy']);
            Route::post('branch-override',         [BranchProductOverrideController::class, 'storeForProduct']);
        });
        Route::apiResource('modifier-groups', ModifierGroupController::class);
        Route::prefix('modifier-groups/{modifierGroup}')->group(function () {
            Route::apiResource('options', ModifierOptionController::class)->shallow();
        });
        Route::apiResource('branch-product-overrides', BranchProductOverrideController::class);
    });
    // Read-only product lookups — used broadly across POS/kitchen/ordering flows
    Route::prefix('products/{product}')->group(function () {
        Route::get('modifier-groups', [ModifierGroupController::class, 'byProduct']);
        Route::get('recipe',          [ProductRecipeController::class, 'byProduct']);
    });

    // ── Floor Plans & Tables ────────────────────────────────────────────────────
    Route::middleware(['permission:floor_plans.manage', 'feature:TABLE_MGMT'])->group(function () {
        Route::apiResource('floor-plans', FloorPlanController::class);
        Route::apiResource('tables', TableController::class);
        Route::get('tables/{table}/qr-code/download', [TableController::class, 'downloadQrCode']);
        Route::post('tables/{table}/qr-code/regenerate', [TableController::class, 'regenerateQrCode']);
        Route::prefix('tables/{table}')->group(function () {
            Route::patch('status', [TableController::class, 'updateStatus']);
        });
    });
    Route::get('tables/{table}/qr-code', [TableController::class, 'qrCode']);
    Route::prefix('tables/{table}')->group(function () {
        // OrderController::byTable() — finds the table's current non-terminal
        // order; a dedicated activeByTable() method was routed here but never
        // existed on the controller (would 500 if ever hit).
        Route::get('active-order',   [OrderController::class, 'byTable']);
        Route::get('reservations',   [ReservationController::class, 'byTable']);
    });

    // ── Reservations ──────────────────────────────────────────────────────────
    Route::middleware(['permission:reservations.manage', 'feature:RESERVATION'])->group(function () {
        Route::apiResource('reservations', ReservationController::class);
        Route::prefix('reservations/{reservation}')->group(function () {
            Route::patch('confirm', [ReservationController::class, 'confirm']);
            Route::patch('seat',    [ReservationController::class, 'seat']);
            Route::patch('cancel',  [ReservationController::class, 'cancel']);
            Route::patch('no-show', [ReservationController::class, 'noShow']);
        });
    });

    // ── Orders ────────────────────────────────────────────────────────────────
    // NOTE: the report/export routes must be registered BEFORE the apiResource
    // below — otherwise `GET orders/{order}` (the resource's `show` route)
    // greedily matches `/orders/report` and `/orders/export` first, passing
    // the literal string "report"/"export" as the order id/number and blowing
    // up with an invalid UUID error.
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('orders/report',  [OrderController::class, 'orderReport']);
        Route::get('orders/export',  [OrderExportController::class, 'export']);
    });
    Route::middleware('permission:orders.manage')->group(function () {
        Route::apiResource('orders', OrderController::class);
        Route::prefix('orders/{order}')->group(function () {
            Route::get('items',             [OrderItemController::class, 'byOrder']);
            Route::post('items',            [OrderItemController::class, 'store']);
            Route::put('items/{item}',      [OrderItemController::class, 'update']);
            Route::delete('items/{item}',   [OrderItemController::class, 'destroy']);

            Route::get('status-history',    [OrderController::class, 'statusHistory']);
            Route::patch('status',          [OrderController::class, 'updateStatus']);
            Route::patch('confirm',         [OrderController::class, 'confirm']);
            Route::patch('prepare',         [OrderController::class, 'prepare']);
            Route::patch('ready',           [OrderController::class, 'ready']);
            Route::patch('complete',        [OrderController::class, 'complete']);
            Route::patch('cancel',          [OrderController::class, 'cancel']);

            Route::get('payments',          [PaymentController::class, 'byOrder']);
            Route::post('payments',         [PaymentController::class, 'store']);

            Route::post('apply-coupon',     [CouponController::class, 'apply']);
            Route::get('kitchen-tickets',   [KitchenDisplayTicketController::class, 'byOrder']);
        });
    });

    // ── Kitchen Display ───────────────────────────────────────────────────────
    Route::middleware(['permission:kitchen.manage', 'feature:KDS'])->group(function () {
        Route::apiResource('kitchen-tickets', KitchenDisplayTicketController::class);
        Route::prefix('kitchen-tickets/{ticket}')->group(function () {
            Route::patch('start',    [KitchenDisplayTicketController::class, 'start']);
            Route::patch('complete', [KitchenDisplayTicketController::class, 'complete']);
            Route::patch('cancel',   [KitchenDisplayTicketController::class, 'cancel']);
        });
    });

    // ── Payments & Refunds ──────────────────────────────────────────────────────
    Route::middleware('permission:payments.manage')->group(function () {
        Route::apiResource('payments', PaymentController::class);
        Route::post('payments/{payment}/refund', [RefundController::class, 'store']);
        Route::apiResource('refunds', RefundController::class)->only(['index', 'show']);

        // ── Cash Drawers ──────────────────────────────────────────────────────
        Route::apiResource('cash-drawers', CashDrawerController::class);
        Route::prefix('cash-drawers')->group(function () {
            Route::post('open',           [CashDrawerController::class, 'open']);
            Route::patch('{drawer}/close', [CashDrawerController::class, 'close']);
        });
    });

    // ── Suppliers ─────────────────────────────────────────────────────────────
    Route::apiResource('suppliers', SupplierController::class)->middleware('permission:suppliers.manage');

    // ── Ingredients ───────────────────────────────────────────────────────────
    Route::apiResource('ingredients', IngredientController::class)->middleware('permission:ingredients.manage');
    Route::prefix('ingredients/{ingredient}')->group(function () {
        Route::get('stock',        [InventoryStockController::class, 'byIngredient']);
        Route::get('transactions', [InventoryTransactionController::class, 'byIngredient']);
    });

    // ── Inventory ─────────────────────────────────────────────────────────────
    // Not gated by feature:INVENTORY — the seeded branch_type_features map only
    // lists INVENTORY for Mart branch types (raw stock tracking); restaurants
    // use this same endpoint for ingredient/recipe stock, which isn't
    // represented as a separate feature code yet. Gating this would break
    // every food tenant's currently-working inventory tracking.
    Route::middleware('permission:inventory.manage')->group(function () {
        Route::apiResource('inventory-stock', InventoryStockController::class);
        Route::post('inventory-stock/adjust', [InventoryStockController::class, 'adjust']);
        Route::apiResource('inventory-transactions', InventoryTransactionController::class)
            ->only(['index', 'show']);
    });

    // ── Product Recipes ───────────────────────────────────────────────────────
    Route::apiResource('product-recipes', ProductRecipeController::class)->middleware('permission:products.manage');

    // ── Purchase Orders ───────────────────────────────────────────────────────
    Route::middleware('permission:purchase_orders.manage')->group(function () {
        Route::apiResource('purchase-orders', PurchaseOrderController::class);
        Route::prefix('purchase-orders/{purchase_order}')->group(function () {
            Route::patch('submit',  [PurchaseOrderController::class, 'submit']);
            Route::patch('confirm', [PurchaseOrderController::class, 'confirm']);
            Route::patch('cancel',  [PurchaseOrderController::class, 'cancel']);
            Route::post('receive',  [PurchaseOrderController::class, 'receive']);
        });
    });

    // ── Customers ─────────────────────────────────────────────────────────────
    Route::middleware('permission:customers.manage')->group(function () {
        Route::apiResource('customers', CustomerController::class);
        Route::prefix('customers/{customer}')->group(function () {
            Route::post('loyalty/add',        [CustomerController::class, 'addPoints']);
            Route::post('loyalty/redeem',     [CustomerController::class, 'redeemPoints']);
            // Only index + store nested
            Route::apiResource('addresses', CustomerAddressController::class)
                ->only(['index', 'store']);
        });
        // show / update / destroy shallow — no customer segment
        Route::apiResource('addresses', CustomerAddressController::class)
            ->only(['show', 'update', 'destroy']);

        // ── Loyalty Transactions ──────────────────────────────────────────────
        Route::apiResource('loyalty-transactions', LoyaltyTransactionController::class)
            ->only(['index', 'show']);
    });
    Route::prefix('customers/{customer}')->group(function () {
        Route::get('orders',  [OrderController::class, 'byCustomer']);
        Route::get('loyalty', [LoyaltyTransactionController::class, 'byCustomer']);
    });

    // ── Promotions & Coupons ────────────────────────────────────────────────────
    Route::middleware('permission:promotions.manage')->group(function () {
        Route::apiResource('promotions', PromotionController::class);
        Route::prefix('promotions/{promotion}')->group(function () {
            Route::get('coupons',        [CouponController::class, 'byPromotion']);
            Route::post('coupons',       [CouponController::class, 'store']);
        });
        Route::apiResource('coupons', CouponController::class);
    });
    // Coupon validation is part of checkout, not coupon management — stays open
    Route::post('coupons/validate', [CouponController::class, 'validate']);

    // ── Reports ───────────────────────────────────────────────────────────────
    Route::middleware('permission:reports.view')->group(function () {
        Route::prefix('reports')->group(function () {
            Route::get('sales',         [DailySalesSummaryController::class, 'index']);
            Route::get('sales/{date}',  [DailySalesSummaryController::class, 'show']);
            // Reuses the existing, working DashboardController::topProducts()
            // implementation rather than duplicating the same aggregation.
            Route::get('top-products',  [DashboardController::class, 'topProducts']);
            // revenue/topCustomers query Order directly (no OrderRepository
            // exists project-wide) — see DailySalesSummaryService for the
            // aggregation logic. staffReport is still not built (no spec).
            Route::get('revenue',       [DailySalesSummaryController::class, 'revenue']);
            Route::get('top-customers', [DailySalesSummaryController::class, 'topCustomers']);
            Route::get('inventory',     [InventoryReportController::class, 'index']);
        });

        // ── Activity Logs ─────────────────────────────────────────────────────
        Route::get('activity-logs',      [AuditLogController::class, 'index']);
        Route::get('activity-logs/{activityLog}', [AuditLogController::class, 'show']);
    });

    // ── Notifications ─────────────────────────────────────────────────────────
    // Registered before apiResource so it isn't swallowed by the
    // {notification} route-model-binding param below.
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::get('notifications/preferences', [NotificationController::class, 'preferences']);
    Route::patch('notifications/preferences', [NotificationController::class, 'updatePreferences']);
    Route::post('notifications/telegram/link', [NotificationController::class, 'telegramLinkUrl']);
    Route::post('notifications/telegram/unlink', [NotificationController::class, 'unlinkTelegram']);
    Route::apiResource('notifications', NotificationController::class)
        ->only(['index', 'show', 'destroy']);
    Route::prefix('notifications')->group(function () {
        Route::patch('{notification}/read', [NotificationController::class, 'markRead']);
        // Frontend calls this with PATCH (matches the single-notification
        // `read` action above) — was registered as POST, a method mismatch
        // that would 405 every "mark all read" click.
        Route::patch('read-all',            [NotificationController::class, 'markAllRead']);
    });


    Route::middleware('permission:menus.manage')->group(function () {
        Route::apiResource('branch-menus', BranchMenuController::class);
        Route::delete('branch-menus/unassign', [BranchMenuController::class, 'unassign']);
    });
    Route::get('branch-menus/branch/{branchId}/available-now', [BranchMenuController::class, 'availableNow']);

    Route::prefix('dashboard')->group(function () {
        Route::get('stats',        [DashboardController::class, 'stats']);
        Route::get('chart',        [DashboardController::class, 'chart']);
        Route::get('live-orders',  [DashboardController::class, 'liveOrders']);
        Route::get('top-products', [DashboardController::class, 'topProducts']);
        Route::get('activity',     [DashboardController::class, 'activity']);
    });
    // ── Hospitality  ───────────────────────────────────────────────────
    Route::prefix('hospitality')->group(function () {
        Route::prefix('/pos')->group(function () {
            Route::get('products', [HospitalityPosController::class, 'productHospitalityPos']);
        });
    });

    Route::prefix('mart')->group(function () {

        Route::middleware('permission:purchase_orders.manage')->group(function () {
            Route::get('purchase-orders', [MartPurchaseOrderController::class, 'index']);
            Route::post('purchase-orders', [MartPurchaseOrderController::class, 'store']);
            Route::get('purchase-orders/{mart_purchase_order}', [MartPurchaseOrderController::class, 'show']);
            Route::put('purchase-orders/{mart_purchase_order}', [MartPurchaseOrderController::class, 'update']);
            Route::delete('purchase-orders/{mart_purchase_order}', [MartPurchaseOrderController::class, 'destroy']);
            Route::post('purchase-orders/{mart_purchase_order}/receive', [MartPurchaseOrderController::class, 'receive']);
            Route::post('purchase-orders/{mart_purchase_order}/cancel', [MartPurchaseOrderController::class, 'cancel']);
        });

        // ── Stock ──────────────────────────────────────────────────────────────
        Route::middleware('permission:inventory.manage')->group(function () {
            Route::post('stock/adjust',     [StockAdjustmentController::class, 'adjust']);
            Route::get('stock/movements',  [StockAdjustmentController::class, 'movements']);
            Route::get('stock/low-stock',  [StockAdjustmentController::class, 'lowStock']);
        });

        Route::get('products',      [MartProductController::class, 'index']);
        Route::get('products/{id}', [MartProductController::class, 'show']);

        // POS sale-taking — product/category browsing + barcode scan stay open,
        // only recording/listing actual sales needs orders.manage
        Route::prefix('/pos')->group(function () {
            Route::get('products', [MartPosController::class, 'products']);
            Route::post('/scan',    [ProductController::class, 'scan']);
            Route::get('categories', [MartPosController::class, 'categories']);
            Route::middleware('permission:orders.manage')->group(function () {
                Route::get('orders', [MartPosController::class, 'index']);
                Route::post('orders', [MartPosController::class, 'store']);
                Route::post('customer-orders', [MartPosController::class, 'storeOrders']);
            });
        });
        Route::middleware('permission:reports.view')->group(function () {
            Route::get('/reports/inventory', [MartPosController::class, 'reportStock']);
            Route::get('/reports/purchases',           [MartPurchaseReportController::class,    'index']);
            Route::get('/reports/product-performance', [MartProductPerformanceController::class, 'index']);
        });
    });

    Route::prefix('coffee')->group(function () {
        // /api/v1/coffee/pos/orders
        Route::prefix('/pos')->group(function () {
            Route::post('orders', [CoffeePOSorderController::class, 'coffeeOrders'])
                ->middleware('permission:orders.manage');
        });
    });

    Route::get('product-units/names', [ProductUnitController::class, 'names']);
    // ── Product Units ──────────────────────────────────────────────────────
    Route::get('products/{product}/units', [ProductUnitController::class, 'index']);
    Route::middleware('permission:products.manage')->group(function () {
        Route::post('products/{product}/units',       [ProductUnitController::class, 'store']);
        Route::put('products/{product}/units/{unit}', [ProductUnitController::class, 'update']);
        Route::delete('products/{product}/units/{unit}', [ProductUnitController::class, 'destroy']);
    });
});


Route::prefix('v1/public')->group(function () {
    Route::get('menu/{branchSlug}',                      [DigitalMenuController::class, 'show']);
    Route::get('menu/{branchSlug}/table/{tableId}',      [DigitalMenuController::class, 'show']);
    Route::get('menu/{branchSlug}/product/{productId}',  [DigitalMenuController::class, 'product']);

    // Orders — no auth, customer places + tracks
    Route::post('orders',                        [OrderController::class, 'store']);
    Route::get('orders/{orderNumber}',           [OrderController::class, 'show']);
    Route::get('orders/table/{tableId}',         [OrderController::class, 'byTable']);

    // website register
    Route::get('business-types', [BusinessTypeController::class, 'index']);
    Route::post('business-register',    [TenantController::class, 'store'])->middleware('throttle:register');
    Route::get('plans', [PlanController::class, 'publicPlans']);
});
