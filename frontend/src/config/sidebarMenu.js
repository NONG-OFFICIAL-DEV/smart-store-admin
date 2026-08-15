// config/sidebarMenu.js
//
// ─── Sidebar menu data — separated from UI ────────────────────────────────────
//
// Pure data: no Vue reactivity, no i18n calls here. `useSidebarMenu` resolves
// `titleKey` via i18n and evaluates `permission`/`visible` against the current
// user's context at render time.
//
// Node shape:
//   key         — stable, unique identifier (used for :key and open-state)
//   titleKey    — i18n key under "menu.*"
//   icon        — mdi icon name
//   path        — route path (leaf items only)
//   permission  — string | string[] — permission code(s) required (array = ANY)
//   visible     — ctx => boolean — escape hatch for role/plan/business-type
//                 rules that aren't permission codes (combined with
//                 `permission` via AND when both are present)
//   badge       — optional short label chip (e.g. food/mart route split)
//   children    — nested items (groups); group auto-hides when empty
//
// To add a new module: add one leaf (or one child inside an existing group).
// Nothing else needs to change — Sidebar.vue and useSidebarMenu.js are generic.

export const SIDEBAR_MENU = [
  // ── Dashboard ────────────────────────────────────────────────────────────
  {
    key: 'dashboard',
    titleKey: 'menu.dashboard',
    icon: 'mdi-view-dashboard-outline',
    path: '/dashboard',
    visible: ctx => !ctx.isSuperAdmin
  },
  {
    key: 'admin-dashboard',
    titleKey: 'menu.dashboard',
    icon: 'mdi-view-dashboard-outline',
    path: '/admin-dashboard',
    visible: ctx => ctx.isSuperAdmin
  },

  // ── Business Management (super admin only) ──────────────────────────────
  {
    key: 'business-management',
    titleKey: 'menu.business_management',
    icon: 'mdi-office-building-outline',
    visible: ctx => ctx.isSuperAdmin,
    children: [
      {
        key: 'tenants',
        titleKey: 'menu.tenant',
        icon: 'mdi-office-building-outline',
        path: '/tenants'
      },
      {
        key: 'pricing-plans',
        titleKey: 'menu.pricing_plans',
        icon: 'mdi-wallet-membership',
        path: '/plan'
      },
      {
        key: 'subscriptions',
        titleKey: 'menu.subscriptions',
        icon: 'mdi-credit-card-outline',
        path: '/subscriptions'
      }
    ]
  },

  // ── Sales & Front of House ───────────────────────────────────────────────
  {
    key: 'sales-front-of-house',
    titleKey: 'menu.sales_operations',
    icon: 'mdi-storefront-outline',
    children: [
      {
        key: 'branches',
        titleKey: 'menu.branches',
        icon: 'mdi-store-outline',
        path: '/branches',
        permission: 'branches.manage',
        // Tenant business data — super admin's permission bypass would
        // otherwise still show this (see router's superAdminAccessible).
        visible: ctx => !ctx.isSuperAdmin
      },
      {
        key: 'operation',
        titleKey: 'menu.operation',
        icon: 'mdi-cash-register',
        path: '/operation',
        permission: 'orders.manage'
      },
      {
        key: 'dining-table',
        titleKey: 'menu.tables',
        icon: 'mdi-table-chair',
        path: '/dining-table',
        permission: 'floor_plans.manage',
        visible: ctx => !ctx.isSuperAdmin && ctx.isFood && ctx.hasPlan('pro')
      },
      {
        key: 'reservations',
        titleKey: 'menu.reservations',
        icon: 'mdi-calendar-check-outline',
        path: '/reservations',
        permission: 'reservations.manage',
        visible: ctx => !ctx.isSuperAdmin && ctx.isFood && ctx.hasPlan('pro')
      },
      {
        key: 'customers',
        titleKey: 'menu.customers',
        icon: 'mdi-account-group-outline',
        path: '/customers',
        permission: 'customers.manage',
        visible: ctx => !ctx.isSuperAdmin
      },
      {
        key: 'loyalty',
        titleKey: 'menu.promotions',
        icon: 'mdi-gift-outline',
        path: '/loyalty',
        permission: 'promotions.manage',
        visible: ctx => !ctx.isSuperAdmin && ctx.hasPlan('pro')
      }
    ]
  },

  // ── Product & Catalog (tenant business data — not for super admin) ──────
  {
    key: 'product-catalog',
    titleKey: 'menu.product_catalog',
    icon: 'mdi-tag-outline',
    visible: ctx => !ctx.isSuperAdmin,
    children: [
      {
        key: 'categories',
        titleKey: 'menu.categories',
        icon: 'mdi-shape-outline',
        path: '/categories',
        permission: 'categories.manage'
      },
      {
        key: 'products',
        titleKey: 'menu.products',
        icon: 'mdi-tag-outline',
        path: '/products',
        permission: 'products.manage'
      },
      {
        key: 'ingredients',
        titleKey: 'menu.ingredients',
        icon: 'mdi-tree',
        path: '/ingredients',
        permission: 'ingredients.manage',
        visible: ctx => ctx.isFood
      },
      {
        key: 'modifiers',
        titleKey: 'menu.modifiers',
        icon: 'mdi-tune-variant',
        path: '/product-modifier-groups',
        permission: 'products.manage',
        visible: ctx => ctx.isFood
      },
      {
        key: 'menus',
        titleKey: 'menu.menus',
        icon: 'mdi-menu',
        path: '/menu-management',
        permission: 'menus.manage',
        visible: ctx => ctx.isFood
      },
      {
        key: 'branch-menus',
        titleKey: 'menu.branch_menus',
        icon: 'mdi-book-open-variant',
        path: '/branch-menus',
        permission: 'menus.manage',
        visible: ctx => ctx.isFood
      }
    ]
  },

  // ── Inventory & Purchasing (tenant business data — not for super admin) ─
  {
    key: 'inventory-purchasing',
    titleKey: 'menu.inventory_purchasing',
    icon: 'mdi-warehouse',
    visible: ctx => !ctx.isSuperAdmin,
    children: [
      {
        key: 'stock-overview-food',
        titleKey: 'menu.stock_overview',
        icon: 'mdi-layers-triple-outline',
        path: '/stocks',
        permission: 'inventory.manage',
        visible: ctx => ctx.isFood
      },
      {
        key: 'stock-overview-mart',
        titleKey: 'menu.stock_overview',
        icon: 'mdi-layers-triple-outline',
        path: '/mart/stock',
        permission: 'inventory.manage',
        visible: ctx => ctx.isMart
      },
      {
        key: 'purchase-orders-food',
        titleKey: 'menu.purchase_order',
        icon: 'mdi-cart-arrow-down',
        path: '/purchases',
        permission: 'purchase_orders.manage',
        visible: ctx => ctx.isFood
      },
      {
        key: 'purchase-orders-mart',
        titleKey: 'menu.purchase_order',
        icon: 'mdi-cart-arrow-down',
        path: '/mart/purchase-order',
        permission: 'purchase_orders.manage',
        visible: ctx => ctx.isMart
      },
      {
        key: 'suppliers',
        titleKey: 'menu.suppliers',
        icon: 'mdi-truck-delivery-outline',
        path: '/suppliers',
        permission: 'suppliers.manage'
      }
    ]
  },

  // ── Staff & Workforce (tenant business data — not for super admin) ───────
  {
    key: 'staff-workforce',
    titleKey: 'menu.staff_workforce',
    icon: 'mdi-account-group-outline',
    visible: ctx => !ctx.isSuperAdmin,
    children: [
      {
        key: 'staff-list',
        titleKey: 'menu.staff_list',
        icon: 'mdi-account-multiple-outline',
        path: '/staff-management',
        permission: 'staff.manage'
      },
      {
        key: 'shift-management',
        titleKey: 'menu.shift',
        icon: 'mdi-clock-outline',
        path: '/shift-management',
        permission: 'shifts.manage'
      },
      {
        key: 'shift-assignments',
        titleKey: 'menu.shift_assign',
        icon: 'mdi-calendar-account-outline',
        path: '/shift-assignments',
        permission: 'shifts.manage'
      }
    ]
  },

  // ── Reports & Analytics (tenant business data — not for super admin) ────
  {
    key: 'reports-analytics',
    titleKey: 'menu.reports_analytics',
    icon: 'mdi-chart-areaspline',
    visible: ctx => !ctx.isSuperAdmin,
    children: [
      {
        key: 'sales-report',
        titleKey: 'menu.orders_analytics',
        icon: 'mdi-chart-bar',
        path: '/orders-reports',
        permission: 'reports.view'
      },
      {
        key: 'purchase-report',
        titleKey: 'menu.purchase_reports',
        icon: 'mdi-file-document-outline',
        path: '/mart/reports/purchases',
        permission: 'reports.view'
      },
      {
        key: 'stock-report',
        titleKey: 'menu.stock_reports',
        icon: 'mdi-clipboard-list-outline',
        path: '/stock-reports',
        permission: 'reports.view'
      }
    ]
  },

  // ── System Administration (super admin only) ────────────────────────────
  {
    key: 'system-administration',
    titleKey: 'menu.system_administration',
    icon: 'mdi-shield-crown-outline',
    visible: ctx => ctx.isSuperAdmin,
    children: [
      {
        key: 'users',
        titleKey: 'menu.users',
        icon: 'mdi-account-cog-outline',
        path: '/users-management'
      },
      {
        key: 'roles',
        titleKey: 'menu.roles',
        icon: 'mdi-shield-account-outline',
        path: '/roles-management'
      },
      {
        key: 'permissions',
        titleKey: 'menu.access_rights',
        icon: 'mdi-lock-outline',
        path: '/role-permissions'
      },
      {
        key: 'activity-log',
        titleKey: 'menu.activity_log',
        icon: 'mdi-history',
        path: '/audit-logs'
      }
    ]
  },

  // ── Setting (super admin only) ───────────────────────────────────────────
  {
    key: 'setting',
    titleKey: 'menu.setting',
    icon: 'mdi-cog-outline',
    visible: ctx => ctx.isSuperAdmin,
    children: [
      {
        key: 'telegram-settings',
        titleKey: 'menu.telegram_settings',
        icon: 'mdi-send-outline',
        path: '/telegram-settings'
      }
    ]
  }
]
