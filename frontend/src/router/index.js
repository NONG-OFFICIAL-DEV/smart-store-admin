import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    name: 'Login',
    component: () => import('@/views/auth/Login.vue'),
    meta: { transition: 'fade' }
  },
  {
    path: '/register',
    name: 'Register',
    component: () => import('@/views/auth/Register.vue'),
    meta: { transition: 'fade' }
  },
  {
    path: '/forgot-password',
    name: 'ForgotPassword',
    component: () => import('@/views/auth/ForgotPassword.vue'),
    meta: { transition: 'fade' }
  },
  {
    path: '/reset-password',
    name: 'ResetPassword',
    component: () => import('@/views/auth/ResetPassword.vue'),
    meta: { transition: 'fade' }
  },
  {
    path: '/force-password-change',
    name: 'ForcePasswordChange',
    component: () => import('@/views/auth/ForcePasswordChange.vue'),
    // Needed regardless of role, or a super admin who must change their
    // password gets bounced in a loop between here and AdminDashboard
    // (step 3b sends them here, step 6b would otherwise send them back).
    meta: { requiresAuth: true, transition: 'fade', superAdminAccessible: true }
  },
  {
    path: '/layout',
    name: 'Layout',
    component: () => import('@/views/layout/Layout.vue'),
    meta: { transition: 'fade' },
    children: [
      {
        path: '/users-management',
        name: 'usersManagement',
        component: () => import('@/views/users/UserManagement.vue'),
        meta: { requiresAuth: true, transition: 'fade', superAdminAccessible: true }
      },
      {
        path: '/tenants',
        name: 'tenants',
        component: () => import('@/views/tenants/TenantView.vue'),
        meta: { requiresAuth: true, transition: 'fade', superAdminAccessible: true }
      },
      {
        path: '/tenants/create',
        name: 'tenant-create',
        component: () => import('@/views/tenants/TenantCreate.vue'),
        meta: { requiresAuth: true, transition: 'slide', superAdminAccessible: true }
      },
      {
        path: '/tenants/:id/edit',
        name: 'tenant-edit',
        component: () => import('@/views/tenants/TenantCreate.vue'),
        meta: { requiresAuth: true, transition: 'slide', superAdminAccessible: true }
      },
      {
        path: '/tenants/:id',
        name: 'tenant-details',
        component: () => import('@/views/tenants/TenantDetails.vue'),
        meta: { requiresAuth: true, transition: 'slide', superAdminAccessible: true }
      },
      {
        path: '/profile',
        name: 'profile',
        component: () => import('@/views/profile/Profile.vue'),
        meta: { requiresAuth: true, transition: 'fade', superAdminAccessible: true }
      },
      {
        path: '/settings-security',
        name: 'settings-security',
        component: () => import('@/views/settings/Settings.vue'),
        meta: { requiresAuth: true, transition: 'fade', superAdminAccessible: true }
      },
      {
        path: '/tenants-billing',
        name: 'tenant-billing',
        component: () => import('@/views/tenants/TenantBilling.vue'),
        meta: { requiresAuth: true, transition: 'fade' }
      },
      {
        path: '/operation',
        name: 'operation',
        component: () => import('@/views/operation/Operation.vue'),
        meta: { requiresAuth: true, transition: 'fade', superAdminAccessible: true }
      },
      {
        path: '/categories',
        name: 'categories',
        component: () => import('@/views/catalogs/CategoryView.vue'),
        meta: { requiresAuth: true, transition: 'fade' }
      },
      {
        path: '/dashboard',
        name: 'Dashboard',
        component: () => import('@/views/Dashboard.vue'),
        meta: { requiresAuth: true, transition: 'fade' }
      },
      {
        path: '/admin-dashboard',
        name: 'AdminDashboard',
        component: () => import('@/views/AdminDashboard.vue'),
        meta: { requiresAuth: true, transition: 'fade', superAdminAccessible: true }
      },
      {
        path: '/telegram-settings',
        name: 'TelegramSettings',
        component: () => import('@/views/TelegramSettings.vue'),
        meta: { requiresAuth: true, transition: 'fade', superAdminAccessible: true }
      },
      {
        path: '/branches',
        name: 'branches',
        component: () => import('@/views/branches/Branch.vue'),
        meta: { requiresAuth: true, transition: 'fade' }
      },
      {
        path: '/roles-management',
        name: 'RolesManagement',
        component: () => import('@/views/rolePermissions/Role.vue'),
        meta: { requiresAuth: true, transition: 'fade', superAdminAccessible: true }
      },
      {
        path: '/role-permissions',
        name: 'RolesPermission',
        component: () => import('@/views/rolePermissions/Permission.vue'),
        meta: { requiresAuth: true, transition: 'fade', superAdminAccessible: true }
      },
      {
        path: '/branch-menus',
        name: 'BranchMenus',
        component: () => import('@/views/catalogs/BranchMenu.vue'),
        meta: { requiresAuth: true, transition: 'fade' }
      },
      {
        path: '/products',
        name: 'Products',
        component: () => import('@/views/products/ProductManagement.vue'),
        meta: {
          requiresAuth: true,
          permission: 'products.manage',
          transition: 'fade'
        }
      },
      {
        path: '/products/create',
        name: 'ProductCreate',
        component: () => import('@/views/products/ProductFormPage.vue'),
        meta: {
          requiresAuth: true,
          permission: 'products.manage',
          transition: 'slide'
        }
      },
      {
        path: '/products/:id/edit',
        name: 'ProductEdit',
        component: () => import('@/views/products/ProductFormPage.vue'),
        meta: { requiresAuth: true, transition: 'slide' }
      },
      {
        path: '/products/:id/units',
        name: 'ProductUnits',
        component: () => import('@/views/products/ProductUnit.vue'),
        meta: {
          requiresAuth: true,
          permission: 'products.manage',
          transition: 'slide'
        }
      },
      {
        path: '/product-details/:id',
        name: 'productDetails',
        component: () => import('@/views/products/ProductDetail.vue'),
        meta: { requiresAuth: true, transition: 'slide' }
      },
      {
        path: '/product-modifier-groups',
        name: 'modifiergroups',
        component: () => import('@/views/products/ProductModifierGroup.vue'),
        meta: { requiresAuth: true, transition: 'fade' }
      },
      {
        path: '/suppliers',
        name: 'Suppliers',
        component: () => import('@/views/stocks/SupplierManagement.vue'),
        meta: { requiresAuth: true, transition: 'fade' }
      },
      {
        path: '/stocks',
        name: 'Stocks',
        component: () => import('@/views/stocks/StockManagement.vue'),
        meta: { requiresAuth: true, transition: 'fade' }
      },
      {
        path: '/purchases',
        name: 'Purchases',
        component: () => import('@/views/stocks/PurchaseManagement.vue'),
        meta: { requiresAuth: true, transition: 'fade' }
      },
      {
        path: '/stock-reports',
        name: 'InventoryReport',
        component: () => import('@/views/reports/StockReport.vue'),
        meta: { requiresAuth: true, transition: 'fade' }
      },
      {
        path: '/audit-logs',
        name: 'AuditLogs',
        component: () => import('@/views/auditLogs/AuditLogPage.vue'),
        meta: { requiresAuth: true, transition: 'fade', superAdminAccessible: true }
      },
      {
        path: '/audit-log/:id',
        name: 'audit-log-details',
        component: () => import('@/views/auditLogs/AuditLogDetails.vue'),
        props: true,
        meta: { transition: 'slide', superAdminAccessible: true }
      },
      {
        path: '/orders-reports',
        name: 'Sales',
        component: () => import('@/views/reports/SaleReport.vue'),
        meta: { requiresAuth: true, transition: 'fade' }
      },
      {
        path: '/menu-management',
        name: 'MenuManagement',
        component: () => import('@/views/catalogs/MenuManagement.vue'),
        meta: { requiresAuth: true, transition: 'fade' }
      },
      {
        path: '/notifications',
        name: 'Notifications',
        component: () => import('@/views/Notification.vue'),
        meta: { requiresAuth: true, transition: 'fade', superAdminAccessible: true }
      },
      {
        path: '/staff-management',
        name: 'Staff',
        component: () => import('@/views/staff/StaffManagement.vue'),
        meta: { requiresAuth: true, transition: 'fade' }
      },
      {
        path: '/shift-assignments',
        name: 'ShiftAssignments',
        component: () => import('@/views/staff/ShiftAssignment.vue'),
        meta: { requiresAuth: true, transition: 'fade' }
      },
      {
        path: '/shift-management',
        name: 'Shifts',
        component: () => import('@/views/staff/ShiftManagement.vue'),
        meta: { transition: 'fade' }
      },
      {
        path: '/dining-table',
        component: () => import('@/views/tables/TableManagement.vue'),
        meta: { transition: 'fade' }
      },
      {
        path: '/reservations',
        component: () => import('@/views/reservations/Reservation.vue'),
        meta: { requiresAuth: true, transition: 'fade' }
      },
      {
        path: '/ingredients',
        component: () => import('@/views/ingredients/Ingredient.vue'),
        meta: { requiresAuth: true, transition: 'fade' }
      },
      {
        path: '/mart/purchase-order',
        name: 'MartPurchaseOrders',
        component: () => import('@/views/mart/MartPurchaseOrder.vue'),
        meta: { requiresAuth: true, transition: 'fade' }
      },
      {
        path: '/mart/purchase-orders/create',
        name: 'MartPurchaseOrderCreate',
        component: () => import('@/views/mart/MartPoForm.vue'),
        meta: { requiresAuth: true, transition: 'slide' }
      },
      {
        path: '/mart/purchase-orders/:id/edit',
        name: 'MartPurchaseOrderEdit',
        component: () => import('@/views/mart/MartPoForm.vue'),
        meta: { requiresAuth: true, transition: 'slide' }
      },
      {
        path: '/mart/stock',
        name: 'MartStock',
        component: () => import('@/views/mart/MartStockManagement.vue'),
        meta: { requiresAuth: true, transition: 'fade' }
      },
      {
        path: '/mart/stock-movements',
        name: 'MartStockMovements',
        component: () => import('@/views/mart/MartStockMovements.vue'),
        meta: { requiresAuth: true, transition: 'fade' }
      },
      {
        path: '/mart/reports/purchases',
        name: 'MartPurchaseReport',
        component: () => import('@/views/mart/MartPurchaseReport.vue'),
        meta: { requiresAuth: true, transition: 'fade' }
      },
      {
        path: '/loyalty',
        name: 'loyalty',
        component: () => import('@/views/loyalty/Loyalty.vue'),
        meta: { requiresAuth: true, transition: 'fade' }
      },
      {
        path: '/plan',
        name: 'plans',
        component: () => import('@/views/subscriptions/Plan.vue'),
        meta: { requiresAuth: true, transition: 'fade', superAdminAccessible: true }
      },
      {
        path: '/subscriptions',
        name: 'subscriptions',
        component: () => import('@/views/subscriptions/Subscriptions.vue'),
        meta: { requiresAuth: true, transition: 'fade', superAdminAccessible: true }
      },
      {
        path: '/subscriptions/history/:tenantId',
        name: 'subscription-history',
        component: () => import('@/views/subscriptions/SubscriptionHistory.vue'),
        meta: { requiresAuth: true, transition: 'slide', superAdminAccessible: true }
      },
      {
        path: '/customers',
        name: 'Customers',
        component: () => import('@/views/customers/CustomerList.vue'),
        meta: {
          requiresAuth: true,
          permission: 'customers.manage',
          transition: 'fade'
        }
      },
      {
        path: '/access-denied',
        name: 'AccessDenied',
        component: () => import('@/views/AccessDeniedView.vue'),
        meta: { transition: 'fade', superAdminAccessible: true }
      },
      {
        path: '/:pathMatch(.*)*',
        name: 'NotFound',
        component: () => import('@/views/NotFoundView.vue'),
        meta: { transition: 'fade' }
      }
    ]
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// Reachable without a token AND without redirecting a logged-in visitor
// away — signup/forgot/reset are legitimate destinations even mid-session
// (e.g. a link opened in a new tab), unlike Login itself which a logged-in
// user should never land back on.
const PUBLIC_AUTH_ROUTES = ['Login', 'Register', 'ForgotPassword', 'ResetPassword']

router.beforeEach(async (to, from, next) => {
  const { useAuthStore } = await import('@/stores/authStore')
  const authStore = useAuthStore()
  const token = localStorage.getItem('token')

  // ── 1. No token → force Login (except the other public auth routes) ───
  if (!token) {
    authStore.$reset() // ← add this, clear stale state
    if (PUBLIC_AUTH_ROUTES.includes(to.name)) return next()
    return next({ name: 'Login' })
  }

  // ── 2. Fetch user if not loaded ────────────────────────────────────────
  if (!authStore.me?.id) {
    try {
      await authStore.fetchMe()
    } catch {
      localStorage.removeItem('token')
      if (PUBLIC_AUTH_ROUTES.includes(to.name)) return next()
      return next({ name: 'Login' })
    }
  }

  // ── 3. Logged-in user hits Login → redirect by role ───────────────────
  if (to.name === 'Login') {
    return next({ name: resolveHome(authStore) })
  }

  // ── 3b. Temporary password still active → force the change-password screen ─
  if (authStore.mustChangePassword && to.name !== 'ForcePasswordChange') {
    return next({ name: 'ForcePasswordChange' })
  }
  if (!authStore.mustChangePassword && to.name === 'ForcePasswordChange') {
    return next({ name: resolveHome(authStore) })
  }

  // ── 4. Unknown route → redirect by role ───────────────────────────────
  if (to.name === 'NotFound') {
    return next({ name: resolveHome(authStore) })
  }

  // ── 5. Route requires a specific permission ────────────────────────────
  if (to.meta.permission && !authStore.can(to.meta.permission)) {
    return next({ name: resolveHome(authStore) })
  }

  // ── 6. Route is admin-only ─────────────────────────────────────────────
  if (to.meta.adminOnly && !authStore.isSuperAdmin && !authStore.isOwner) {
    return next({ name: resolveHome(authStore) })
  }

  // ── 6b. Super admin browsing tenant-owned business data ────────────────
  // TenantScope deliberately bypasses tenant filtering for super admins on
  // the backend (support/oversight access to any tenant's raw data via
  // API/tinker) — but that's not the same as the admin *frontend* freely
  // browsing every tenant's menus/products/customers/etc. mixed together
  // with no tenant context, same boundary photo-studio-saas enforces via
  // separate /admin vs / route groups. Secure-by-default: a route needs
  // `superAdminAccessible: true` to be reachable by a super admin; any
  // route added later without it is tenant-territory by default.
  if (authStore.isSuperAdmin && !to.meta.superAdminAccessible) {
    return next({ name: 'AccessDenied', query: { redirect: to.fullPath } })
  }

  const detailToList =
    from.meta.transition === 'slide' && to.meta.transition === 'fade'

  // List/dashboard going to detail/form pages → slide
  const listToDetail =
    from.meta.transition === 'fade' && to.meta.transition === 'slide'

  if (listToDetail) {
    to.meta.transitionName = 'slide'
  } else if (detailToList) {
    to.meta.transitionName = 'slide-right'
  } else {
    to.meta.transitionName = to.meta.transition || 'fade'
  }
  next()
})

// ── Helper: pick landing page based on role ────────────────────────────────
function resolveHome(authStore) {
  return authStore.isOwner ? 'Dashboard' : 'AdminDashboard'
  // || authStore.isSuperAdmin)
}

export default router
