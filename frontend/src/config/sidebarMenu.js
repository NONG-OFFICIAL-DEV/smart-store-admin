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
//   titleKey    — i18n key (any namespace — dotted path, e.g. "menu.orders"
//                 or "settings.title" if reusing a label from elsewhere)
//   icon        — mdi icon name
//   path        — route path (leaf items only) — a plain string, OR
//                 `ctx => string` when the destination depends on tenant
//                 context (e.g. business type)
//   permission  — string | string[] — permission code(s) required (array = ANY)
//   visible     — ctx => boolean — escape hatch for role/plan/business-type
//                 rules that aren't permission codes (combined with
//                 `permission` via AND when both are present)
//   badge       — optional short label chip (e.g. food/mart route split)
//   emphasize   — group-level flag: renders with visual emphasis (POS is the
//                 primary workspace and should stand out from the rest)
//   children    — nested items (groups); group auto-hides when empty.
//                 NOTE: only 2 levels are supported (Sidebar.vue renders a
//                 group's children as flat leaves) — do not nest a group
//                 inside a group's children.
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

  // ── Business Management (super admin only — their own operational area) ─
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
      }
    ]
  },

  // ── POS — the primary workspace for tenant staff/owners ──────────────────
  {
    key: 'pos',
    titleKey: 'menu.pos',
    icon: 'mdi-point-of-sale',
    emphasize: true,
    visible: ctx => !ctx.isSuperAdmin,
    children: [
      {
        key: 'quick-sale',
        titleKey: 'menu.quick_sale',
        icon: 'mdi-lightning-bolt-outline',
        // Deep-link straight into the right POS for this tenant's business
        // type — skips the Operation.vue launcher for the common case.
        // Falls back to the launcher only when neither category matches.
        path: ctx => (ctx.isMart ? '/pos/retail' : ctx.isFood ? '/pos/food' : '/operation'),
        permission: 'orders.manage'
      },
      {
        key: 'orders',
        titleKey: 'menu.orders',
        icon: 'mdi-receipt-text-outline',
        path: '/orders',
        permission: 'reports.view'
      }
    ]
  },

  // ── Operations — catalog, inventory, front-of-house, customers ──────────
  {
    key: 'operations',
    titleKey: 'menu.ops',
    icon: 'mdi-storefront-outline',
    visible: ctx => !ctx.isSuperAdmin,
    children: [
      {
        key: 'kitchen',
        titleKey: 'menu.kitchen',
        icon: 'mdi-chef-hat',
        path: '/kitchen',
        permission: 'kitchen.manage',
        visible: ctx => ctx.isFood && ctx.hasFeature('KDS')
      },
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
        key: 'dining-table',
        titleKey: 'menu.tables',
        icon: 'mdi-table-chair',
        path: '/dining-table',
        permission: 'floor_plans.manage',
        visible: ctx => !ctx.isSuperAdmin && ctx.isFood && ctx.hasPlan('pro') && ctx.hasFeature('TABLE_MGMT')
      },
      {
        key: 'reservations',
        titleKey: 'menu.reservations',
        icon: 'mdi-calendar-check-outline',
        path: '/reservations',
        permission: 'reservations.manage',
        visible: ctx => !ctx.isSuperAdmin && ctx.isFood && ctx.hasPlan('pro') && ctx.hasFeature('RESERVATION')
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
      },
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
      },
      {
        key: 'stock-overview-food',
        titleKey: 'menu.stock_overview',
        icon: 'mdi-layers-triple-outline',
        path: '/stocks',
        permission: 'inventory.manage',
        // Not gated by hasFeature('INVENTORY') — the seeded branch_type_features
        // map only lists INVENTORY for Mart branch types (raw stock tracking);
        // restaurants use this same screen for ingredient/recipe stock, which
        // isn't represented as a separate feature code yet. Gating this would
        // hide a screen every food tenant already actively uses.
        visible: ctx => ctx.isFood
      },
      {
        key: 'stock-overview-mart',
        titleKey: 'menu.stock_overview',
        icon: 'mdi-layers-triple-outline',
        path: '/mart/stock',
        permission: 'inventory.manage',
        visible: ctx => ctx.isMart && ctx.hasFeature('INVENTORY')
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

  // ── Business — reports and workforce management ──────────────────────────
  {
    key: 'business',
    titleKey: 'menu.business',
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
        permission: 'reports.view',
        visible: ctx => ctx.isMart
      },
      {
        key: 'stock-report',
        titleKey: 'menu.stock_reports',
        icon: 'mdi-clipboard-list-outline',
        path: '/stock-reports',
        permission: 'reports.view',
        visible: ctx => ctx.isMart
      },
      {
        key: 'revenue-report',
        titleKey: 'menu.revenue_reports',
        icon: 'mdi-cash-multiple',
        path: '/revenue-reports',
        permission: 'reports.view'
      },
      {
        key: 'ingredient-inventory-report',
        titleKey: 'menu.ingredient_inventory_reports',
        icon: 'mdi-food-variant',
        visible: ctx => ctx.isFood,
        path: '/ingredient-inventory-reports',
        permission: 'reports.view'
      },
      {
        key: 'cash-register',
        titleKey: 'menu.cash_register',
        icon: 'mdi-cash-register',
        path: '/cash-register',
        permission: 'payments.manage'
      },
      {
        key: 'workforce',
        titleKey: 'workforce.title',
        icon: 'mdi-account-multiple-outline',
        path: '/workforce',
        // Any = visible if the user can reach at least one of the 3 tabs
        // merged into this page (Staff / Shifts / Assignments).
        permission: ['staff.manage', 'shifts.manage']
      }
    ]
  },

  // ── Settings — pinned last; a general entry for everyone, plus super
  //    admin-only system administration folded in underneath ──────────────
  {
    key: 'system',
    titleKey: 'menu.setting',
    icon: 'mdi-cog-outline',
    visible: () => true,
    children: [
      {
        key: 'general-settings',
        titleKey: 'settings.title',
        icon: 'mdi-shield-lock-outline',
        path: '/settings-security',
        visible: () => true
      },
      {
        key: 'telegram-settings',
        titleKey: 'menu.telegram_settings',
        icon: 'mdi-send-outline',
        path: '/telegram-settings',
        visible: ctx => ctx.isSuperAdmin
      },
      {
        key: 'roles',
        titleKey: 'menu.roles',
        icon: 'mdi-shield-account-outline',
        path: '/roles-management',
        visible: ctx => ctx.isSuperAdmin
      },
      {
        key: 'permissions',
        titleKey: 'menu.access_rights',
        icon: 'mdi-lock-outline',
        path: '/role-permissions',
        visible: ctx => ctx.isSuperAdmin
      },
      {
        key: 'activity-log',
        titleKey: 'menu.activity_log',
        icon: 'mdi-history',
        path: '/audit-logs',
        visible: ctx => ctx.isSuperAdmin
      }
    ]
  }
]
