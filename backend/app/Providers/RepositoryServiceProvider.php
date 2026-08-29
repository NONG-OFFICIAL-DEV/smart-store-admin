<?php

namespace App\Providers;

use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\BranchHourRepositoryInterface;
use App\Repositories\Contracts\BranchMenuRepositoryInterface;
use App\Repositories\Contracts\BranchProductOverrideRepositoryInterface;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\BusinessTypeRepositoryInterface;
use App\Repositories\Contracts\CashDrawerRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\CouponRepositoryInterface;
use App\Repositories\Contracts\CustomerAddressRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\DailySalesSummaryRepositoryInterface;
use App\Repositories\Contracts\FloorPlanRepositoryInterface;
use App\Repositories\Contracts\IngredientRepositoryInterface;
use App\Repositories\Contracts\InventoryStockRepositoryInterface;
use App\Repositories\Contracts\InventoryTransactionRepositoryInterface;
use App\Repositories\Contracts\KitchenDisplayTicketRepositoryInterface;
use App\Repositories\Contracts\LoyaltyTransactionRepositoryInterface;
use App\Repositories\Contracts\MartPurchaseOrderRepositoryInterface;
use App\Repositories\Contracts\MenuRepositoryInterface;
use App\Repositories\Contracts\ModifierGroupRepositoryInterface;
use App\Repositories\Contracts\ModifierOptionRepositoryInterface;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\PlanFeatureListingRepositoryInterface;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\PromotionRepositoryInterface;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\ShiftRepositoryInterface;
use App\Repositories\Contracts\StaffRepositoryInterface;
use App\Repositories\Contracts\StaffShiftRepositoryInterface;
use App\Repositories\Contracts\SubscriptionPlanHistoryRepositoryInterface;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use App\Repositories\Contracts\TableRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\TenantSubscriptionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\ActivityLogRepository;
use App\Repositories\Eloquent\BranchHourRepository;
use App\Repositories\Eloquent\BranchMenuRepository;
use App\Repositories\Eloquent\BranchProductOverrideRepository;
use App\Repositories\Eloquent\BranchRepository;
use App\Repositories\Eloquent\BusinessTypeRepository;
use App\Repositories\Eloquent\CashDrawerRepository;
use App\Repositories\Eloquent\CategoryRepository;
use App\Repositories\Eloquent\CouponRepository;
use App\Repositories\Eloquent\CustomerAddressRepository;
use App\Repositories\Eloquent\CustomerRepository;
use App\Repositories\Eloquent\DailySalesSummaryRepository;
use App\Repositories\Eloquent\FloorPlanRepository;
use App\Repositories\Eloquent\IngredientRepository;
use App\Repositories\Eloquent\InventoryStockRepository;
use App\Repositories\Eloquent\InventoryTransactionRepository;
use App\Repositories\Eloquent\KitchenDisplayTicketRepository;
use App\Repositories\Eloquent\LoyaltyTransactionRepository;
use App\Repositories\Eloquent\MartPurchaseOrderRepository;
use App\Repositories\Eloquent\MenuRepository;
use App\Repositories\Eloquent\ModifierGroupRepository;
use App\Repositories\Eloquent\ModifierOptionRepository;
use App\Repositories\Eloquent\NotificationRepository;
use App\Repositories\Eloquent\PaymentRepository;
use App\Repositories\Eloquent\PermissionRepository;
use App\Repositories\Eloquent\PlanFeatureListingRepository;
use App\Repositories\Eloquent\PlanRepository;
use App\Repositories\Eloquent\ProductRepository;
use App\Repositories\Eloquent\PromotionRepository;
use App\Repositories\Eloquent\PurchaseOrderRepository;
use App\Repositories\Eloquent\RefundRepository;
use App\Repositories\Eloquent\ReservationRepository;
use App\Repositories\Eloquent\RoleRepository;
use App\Repositories\Eloquent\ShiftRepository;
use App\Repositories\Eloquent\StaffRepository;
use App\Repositories\Eloquent\StaffShiftRepository;
use App\Repositories\Eloquent\SubscriptionPlanHistoryRepository;
use App\Repositories\Eloquent\SupplierRepository;
use App\Repositories\Eloquent\TableRepository;
use App\Repositories\Eloquent\TenantRepository;
use App\Repositories\Eloquent\TenantSubscriptionRepository;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Interface => implementation bindings. Add one line per resource as
     * it migrates onto the Repository/Service pattern — don't pre-bind a
     * resource that hasn't been migrated yet.
     *
     * Named $repositoryBindings (not $bindings) because Laravel's
     * ServiceProvider auto-registers any public `$bindings` property
     * itself — reusing that name here would double-register / conflict.
     */
    protected array $repositoryBindings = [
        CustomerRepositoryInterface::class => CustomerRepository::class,
        SupplierRepositoryInterface::class => SupplierRepository::class,
        IngredientRepositoryInterface::class => IngredientRepository::class,
        StaffRepositoryInterface::class => StaffRepository::class,
        RoleRepositoryInterface::class => RoleRepository::class,
        PermissionRepositoryInterface::class => PermissionRepository::class,
        ShiftRepositoryInterface::class => ShiftRepository::class,
        StaffShiftRepositoryInterface::class => StaffShiftRepository::class,
        BranchRepositoryInterface::class => BranchRepository::class,
        BranchHourRepositoryInterface::class => BranchHourRepository::class,
        BranchMenuRepositoryInterface::class => BranchMenuRepository::class,
        BranchProductOverrideRepositoryInterface::class => BranchProductOverrideRepository::class,
        TableRepositoryInterface::class => TableRepository::class,
        ReservationRepositoryInterface::class => ReservationRepository::class,
        FloorPlanRepositoryInterface::class => FloorPlanRepository::class,
        MenuRepositoryInterface::class => MenuRepository::class,
        CategoryRepositoryInterface::class => CategoryRepository::class,
        ModifierGroupRepositoryInterface::class => ModifierGroupRepository::class,
        ModifierOptionRepositoryInterface::class => ModifierOptionRepository::class,
        PromotionRepositoryInterface::class => PromotionRepository::class,
        CouponRepositoryInterface::class => CouponRepository::class,
        UserRepositoryInterface::class => UserRepository::class,
        PaymentRepositoryInterface::class => PaymentRepository::class,
        RefundRepositoryInterface::class => RefundRepository::class,
        ActivityLogRepositoryInterface::class => ActivityLogRepository::class,
        CustomerAddressRepositoryInterface::class => CustomerAddressRepository::class,
        CashDrawerRepositoryInterface::class => CashDrawerRepository::class,
        InventoryStockRepositoryInterface::class => InventoryStockRepository::class,
        InventoryTransactionRepositoryInterface::class => InventoryTransactionRepository::class,
        KitchenDisplayTicketRepositoryInterface::class => KitchenDisplayTicketRepository::class,
        LoyaltyTransactionRepositoryInterface::class => LoyaltyTransactionRepository::class,
        NotificationRepositoryInterface::class => NotificationRepository::class,
        BusinessTypeRepositoryInterface::class => BusinessTypeRepository::class,
        PlanRepositoryInterface::class => PlanRepository::class,
        PlanFeatureListingRepositoryInterface::class => PlanFeatureListingRepository::class,
        PurchaseOrderRepositoryInterface::class => PurchaseOrderRepository::class,
        MartPurchaseOrderRepositoryInterface::class => MartPurchaseOrderRepository::class,
        DailySalesSummaryRepositoryInterface::class => DailySalesSummaryRepository::class,
        TenantRepositoryInterface::class => TenantRepository::class,
        ProductRepositoryInterface::class => ProductRepository::class,
        TenantSubscriptionRepositoryInterface::class => TenantSubscriptionRepository::class,
        SubscriptionPlanHistoryRepositoryInterface::class => SubscriptionPlanHistoryRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->repositoryBindings as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }
    }
}
