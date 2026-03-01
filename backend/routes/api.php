<?php

use Illuminate\Support\Facades\Route;
// ── Auth ───────────────────────────────────────────────────────────────────
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

// ── Multi-Tenancy ──────────────────────────────────────────────────────────
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
use App\Http\Controllers\Api\AllergenController;
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
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\BranchMenuController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ShiftAssignmentController;

Route::get('/test', function () {
    return response()->json([
        'message' => 'API is working'
    ]);
});

Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:api')->get('/me', [AuthController::class, 'me']);
Route::middleware('auth:api')->post('/logout', [AuthController::class, 'logout']);

Route::middleware('jwt.auth')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
    });
});


// =============================================================================
// PUBLIC ROUTES (no auth required)
// =============================================================================
// Route::prefix('v1')->group(function () {

//     // ── Authentication ────────────────────────────────────────────────────────
//     Route::prefix('auth:api')->group(function () {
//         Route::post('login',          [AuthController::class, 'login']);
//         Route::post('register',       [AuthController::class, 'register']);
//         Route::post('forgot-password',[AuthController::class, 'forgotPassword']);
//         Route::post('reset-password', [AuthController::class, 'resetPassword']);
//         Route::post('refresh',        [AuthController::class, 'refresh']);
//     });

// });

// =============================================================================
// PROTECTED ROUTES (auth required)
// =============================================================================
Route::prefix('v1')->middleware(['jwt.auth'])->group(function () {

    // ── Auth (protected) ──────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        // Route::post('logout',  [AuthController::class, 'logout']);
        // Route::get('me',       [AuthController::class, 'me']);
        Route::put('profile',  [AuthController::class, 'updateProfile']);
        Route::put('password', [AuthController::class, 'changePassword']);
    });

    // ── Tenants ───────────────────────────────────────────────────────────────
    Route::apiResource('tenants', TenantController::class);

    // ── Branches ──────────────────────────────────────────────────────────────
    Route::apiResource('branches', BranchController::class);
    Route::prefix('branches/{branch}')->group(function () {
        Route::get('hours',          [BranchHourController::class, 'index']);
        Route::post('hours',         [BranchHourController::class, 'store']);
        Route::put('hours/{hour}',   [BranchHourController::class, 'update']);
        Route::delete('hours/{hour}', [BranchHourController::class, 'destroy']);

        Route::get('staff',          [StaffController::class, 'byBranch']);
        Route::get('orders',         [OrderController::class, 'byBranch']);
        Route::get('tables',         [TableController::class, 'byBranch']);
        Route::get('floor-plans',    [FloorPlanController::class, 'byBranch']);
        Route::get('inventory',      [InventoryStockController::class, 'byBranch']);
        Route::get('reservations',   [ReservationController::class, 'byBranch']);
        Route::get('sales-summary',  [DailySalesSummaryController::class, 'byBranch']);
        Route::post('sales-summary/generate', [DailySalesSummaryController::class, 'generate']);
    });

    // ── Users ─────────────────────────────────────────────────────────────────
    Route::apiResource('users', UserController::class);

    // ── Roles & Permissions ───────────────────────────────────────────────────
    Route::apiResource('roles',       RoleController::class);
    Route::apiResource('permissions', PermissionController::class);
    Route::post('roles/{role}/permissions/sync', [RoleController::class, 'syncPermissions']);

    // ── Staff ─────────────────────────────────────────────────────────────────
    Route::apiResource('staff', StaffController::class);
    Route::prefix('staff/{staff}')->group(function () {
        Route::get('shifts',       [ShiftController::class, 'byStaff']);
        Route::post('clock-in',    [ShiftController::class, 'clockIn']);
        Route::post('clock-out',   [ShiftController::class, 'clockOut']);
    });
        // ── Shifts (definitions) ──────────────────────────────────────────────────
    Route::apiResource('shifts', ShiftController::class);

    // ── Shift Assignments (staff_shifts) ──────────────────────────────────────
    Route::apiResource('shift-assignments', ShiftAssignmentController::class);

    // Clock in/out endpoints
    Route::post('shift-assignments/{id}/clock-in',  [ShiftAssignmentController::class, 'clockIn']);
    Route::post('shift-assignments/{id}/clock-out', [ShiftAssignmentController::class, 'clockOut']);


    // ── Shifts ────────────────────────────────────────────────────────────────
    Route::apiResource('shifts', ShiftController::class);

    // ── Menus ─────────────────────────────────────────────────────────────────
    Route::apiResource('menus', MenuController::class);
    Route::prefix('menus/{menu}')->group(function () {
        Route::get('categories',          [CategoryController::class, 'byMenu']);
        Route::post('branches/sync',      [MenuController::class, 'syncBranches']);
    });

    // ── Categories ────────────────────────────────────────────────────────────
    Route::apiResource('categories', CategoryController::class);
    Route::prefix('categories/{category}')->group(function () {
        Route::get('products', [ProductController::class, 'byCategory']);
    });

    // ── Products ──────────────────────────────────────────────────────────────
    Route::apiResource('products', ProductController::class);
    Route::apiResource('product-variants', ProductVariantController::class);
    Route::prefix('products/{product}')->group(function () {
        Route::apiResource('variants',         ProductVariantController::class)->shallow();
        Route::get('modifier-groups',          [ModifierGroupController::class, 'byProduct']);
        Route::post('modifier-groups/sync',    [ProductController::class, 'attachModifierGroups']);
        Route::get('allergens',                [AllergenController::class, 'byProduct']);
        Route::post('allergens/sync',          [ProductController::class, 'syncAllergens']);
        Route::get('recipe',                   [ProductRecipeController::class, 'byProduct']);
        Route::post('recipe',                  [ProductRecipeController::class, 'store']);
        Route::put('recipe/{recipe}',          [ProductRecipeController::class, 'update']);
        Route::delete('recipe/{recipe}',       [ProductRecipeController::class, 'destroy']);
        Route::post('branch-override',         [BranchProductOverrideController::class, 'store']);
    });

    // ── Modifier Groups ───────────────────────────────────────────────────────
    Route::apiResource('modifier-groups', ModifierGroupController::class);
    Route::prefix('modifier-groups/{modifierGroup}')->group(function () {
        Route::apiResource('options', ModifierOptionController::class)->shallow();
    });

    // ── Allergens ─────────────────────────────────────────────────────────────
    Route::apiResource('allergens', AllergenController::class);

    // ── Branch Product Overrides ──────────────────────────────────────────────
    Route::apiResource('branch-product-overrides', BranchProductOverrideController::class);

    // ── Floor Plans ───────────────────────────────────────────────────────────
    Route::apiResource('floor-plans', FloorPlanController::class);

    // ── Tables ────────────────────────────────────────────────────────────────
    Route::apiResource('tables', TableController::class);
    Route::prefix('tables/{table}')->group(function () {
        Route::patch('status',       [TableController::class, 'updateStatus']);
        Route::get('active-order',   [OrderController::class, 'activeByTable']);
        Route::get('reservations',   [ReservationController::class, 'byTable']);
    });

    // ── Reservations ──────────────────────────────────────────────────────────
    Route::apiResource('reservations', ReservationController::class);
    Route::prefix('reservations/{reservation}')->group(function () {
        Route::patch('confirm', [ReservationController::class, 'confirm']);
        Route::patch('seat',    [ReservationController::class, 'seat']);
        Route::patch('cancel',  [ReservationController::class, 'cancel']);
        Route::patch('no-show', [ReservationController::class, 'noShow']);
    });

    // ── Orders ────────────────────────────────────────────────────────────────
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

    // ── Kitchen Display ───────────────────────────────────────────────────────
    Route::apiResource('kitchen-tickets', KitchenDisplayTicketController::class);
    Route::prefix('kitchen-tickets/{ticket}')->group(function () {
        Route::patch('start',    [KitchenDisplayTicketController::class, 'start']);
        Route::patch('complete', [KitchenDisplayTicketController::class, 'complete']);
        Route::patch('cancel',   [KitchenDisplayTicketController::class, 'cancel']);
    });

    // ── Payments ──────────────────────────────────────────────────────────────
    Route::apiResource('payments', PaymentController::class);
    Route::post('payments/{payment}/refund', [RefundController::class, 'store']);

    // ── Refunds ───────────────────────────────────────────────────────────────
    Route::apiResource('refunds', RefundController::class)->only(['index', 'show']);

    // ── Cash Drawers ──────────────────────────────────────────────────────────
    Route::apiResource('cash-drawers', CashDrawerController::class);
    Route::prefix('cash-drawers')->group(function () {
        Route::post('open',           [CashDrawerController::class, 'open']);
        Route::patch('{drawer}/close', [CashDrawerController::class, 'close']);
    });

    // ── Suppliers ─────────────────────────────────────────────────────────────
    Route::apiResource('suppliers', SupplierController::class);

    // ── Ingredients ───────────────────────────────────────────────────────────
    Route::apiResource('ingredients', IngredientController::class);
    Route::prefix('ingredients/{ingredient}')->group(function () {
        Route::get('stock',        [InventoryStockController::class, 'byIngredient']);
        Route::get('transactions', [InventoryTransactionController::class, 'byIngredient']);
    });

    // ── Inventory Stock ───────────────────────────────────────────────────────
    Route::apiResource('inventory-stock', InventoryStockController::class);
    Route::post('inventory-stock/adjust', [InventoryStockController::class, 'adjust']);

    // ── Inventory Transactions ────────────────────────────────────────────────
    Route::apiResource('inventory-transactions', InventoryTransactionController::class)
        ->only(['index', 'show']);

    // ── Product Recipes ───────────────────────────────────────────────────────
    Route::apiResource('product-recipes', ProductRecipeController::class);

    // ── Purchase Orders ───────────────────────────────────────────────────────
    Route::apiResource('purchase-orders', PurchaseOrderController::class);
    Route::prefix('purchase-orders/{purchaseOrder}')->group(function () {
        Route::patch('submit',  [PurchaseOrderController::class, 'submit']);
        Route::patch('confirm', [PurchaseOrderController::class, 'confirm']);
        Route::patch('cancel',  [PurchaseOrderController::class, 'cancel']);
        Route::post('receive',  [PurchaseOrderController::class, 'receive']);
    });

    // ── Customers ─────────────────────────────────────────────────────────────
    Route::apiResource('customers', CustomerController::class);
    Route::prefix('customers/{customer}')->group(function () {
        Route::get('orders',              [OrderController::class, 'byCustomer']);
        Route::get('loyalty',             [LoyaltyTransactionController::class, 'byCustomer']);
        Route::post('loyalty/add',        [CustomerController::class, 'addPoints']);
        Route::post('loyalty/redeem',     [CustomerController::class, 'redeemPoints']);
        Route::apiResource('addresses',   CustomerAddressController::class)->shallow();
    });

    // ── Loyalty Transactions ──────────────────────────────────────────────────
    Route::apiResource('loyalty-transactions', LoyaltyTransactionController::class)
        ->only(['index', 'show']);

    // ── Promotions ────────────────────────────────────────────────────────────
    Route::apiResource('promotions', PromotionController::class);
    Route::prefix('promotions/{promotion}')->group(function () {
        Route::get('coupons',        [CouponController::class, 'byPromotion']);
        Route::post('coupons',       [CouponController::class, 'store']);
    });

    // ── Coupons ───────────────────────────────────────────────────────────────
    Route::apiResource('coupons', CouponController::class);
    Route::post('coupons/validate', [CouponController::class, 'validate']);

    // ── Reports ───────────────────────────────────────────────────────────────
    Route::prefix('reports')->group(function () {
        Route::get('sales',         [DailySalesSummaryController::class, 'index']);
        Route::get('sales/{date}',  [DailySalesSummaryController::class, 'show']);
        Route::get('top-products',  [DailySalesSummaryController::class, 'topProducts']);
        Route::get('top-customers', [DailySalesSummaryController::class, 'topCustomers']);
        Route::get('revenue',       [DailySalesSummaryController::class, 'revenue']);
        Route::get('staff',         [DailySalesSummaryController::class, 'staffReport']);
        Route::get('inventory',     [InventoryStockController::class, 'report']);
    });

    // ── Activity Logs ─────────────────────────────────────────────────────────
    Route::get('activity-logs',      [ActivityLogController::class, 'index']);
    Route::get('activity-logs/{id}', [ActivityLogController::class, 'show']);

    // ── Notifications ─────────────────────────────────────────────────────────
    Route::apiResource('notifications', NotificationController::class)
        ->only(['index', 'show', 'destroy']);
    Route::prefix('notifications')->group(function () {
        Route::patch('{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('read-all',             [NotificationController::class, 'markAllRead']);
    });


    Route::apiResource('branch-menus', BranchMenuController::class);
    Route::delete('branch-menus/unassign', [BranchMenuController::class, 'unassign']);
    Route::get('branch-menus/branch/{branchId}/available-now', [BranchMenuController::class, 'availableNow']);
});
