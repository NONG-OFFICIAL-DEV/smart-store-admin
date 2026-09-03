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
        key: 'kitchen',
        titleKey: 'menu.kitchen',
        icon: 'mdi-chef-hat',
        path: '/kitchen',
        permission: 'kitchen.manage',
        visible: ctx => ctx.isFood && ctx.hasFeature('KDS')
      }
    ]
  },

  // ── Products — full sales catalog (products, categories, modifiers,
  //    menus, branch menus, ingredients) as one tabbed page. See
  //    views/catalogs/CatalogHub.vue.
  {
    key: 'catalog',
    titleKey: 'catalog_hub.title',
    icon: 'mdi-tag-multiple-outline',
    path: '/catalog',
    permission: ['categories.manage', 'products.manage', 'menus.manage', 'ingredients.manage'],
    visible: ctx => !ctx.isSuperAdmin
  },

  // ── Inventory — stock, purchase orders (food+mart variants), suppliers ──
  //    merged into one tabbed page. See views/stocks/InventoryHub.vue.
  {
    key: 'inventory',
    titleKey: 'inventory_hub.title',
    icon: 'mdi-warehouse',
    path: '/inventory',
    permission: ['inventory.manage', 'purchase_orders.manage', 'suppliers.manage'],
    visible: ctx => !ctx.isSuperAdmin
  },

  // ── Customers — customer list + loyalty/promotions merged into one ──────
  //    tabbed page. See views/customers/CustomersHub.vue.
  {
    key: 'customers',
    titleKey: 'customers_hub.title',
    icon: 'mdi-account-group-outline',
    path: '/customers-hub',
    permission: ['customers.manage', 'promotions.manage'],
    visible: ctx => !ctx.isSuperAdmin
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
    titleKey: 'menu.system',
    icon: 'mdi-cog-outline',
    visible: () => true,
    children: [
      {
        key: 'settings-hub',
        titleKey: 'settings_hub.title',
        icon: 'mdi-tune-variant',
        path: '/settings',
        permission: 'branches.manage',
        // Company Info + Branch tabs — tenant business data — super admin's
        // permission bypass would otherwise still show this (see router's
        // superAdminAccessible).
        visible: ctx => !ctx.isSuperAdmin
      },
      {
        key: 'general-settings',
        titleKey: 'menu.security',
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
        titleKey: 'menu.users_roles',
        icon: 'mdi-shield-account-outline',
        path: '/roles-management',
        permission: 'roles.manage',
        // Tenant owners (and any staff explicitly granted roles.manage) can
        // now create/edit roles for their own tenant — super admin's
        // permission bypass would otherwise still show this (see router's
        // superAdminAccessible); the raw permission-catalog page below
        // stays super-admin-only, it's shared system-wide reference data.
        visible: ctx => !ctx.isSuperAdmin
      },
      {
        key: 'permissions',
        titleKey: 'menu.access_rights',
        icon: 'mdi-lock-outline',
        path: '/role-permissions',
        visible: ctx => ctx.isSuperAdmin
      },
      {
        key: 'system-categories',
        titleKey: 'menu.system_categories',
        icon: 'mdi-tag-multiple-outline',
        path: '/system-categories',
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
