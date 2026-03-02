import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    name: 'Login',
    component: () => import('@/views/auth/Login.vue')
  },
  {
    path: '/layout',
    component: () => import('@/views/layout/Layout.vue'),
    children: [
      // User Management
      {
        path: '/users-management',
        name: 'usersManagement',
        component: () => import('@/views/users/UserManagement.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: '/tenants',
        name: 'tenants',
        component: () => import('@/views/tenants/TenantView.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: '/categories',
        name: 'categories',
        component: () => import('@/views/catalogs/CategoryView.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: '/dashboard',
        name: 'Dashboard',
        component: () => import('@/views/Dashboard.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: '/admin-dashboard',
        name: 'AdminDashboard',
        component: () => import('@/views/AdminDashboardPage.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: '/branches',
        name: 'branches',
        component: () => import('@/views/branches/Branch.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: '/roles-management',
        name: 'RolesManagement',
        component: () => import('@/views/rolePermissions/Role.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: '/role-permissions',
        name: 'RolesPermission',
        component: () => import('@/views/rolePermissions/Permission.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: '/branch-menus',
        name: 'BranchMenus',
        component: () => import('@/views/catalogs/BranchMenu.vue'),
        meta: { requiresAuth: true }
      },
      // Stock Management Pages
      {
        path: '/products',
        name: 'Products',
        component: () => import('@/views/products/ProductManagement.vue'),
        meta: { requiresAuth: true, permission: 'products.view'   } 
      },
      {
        path: '/product-details/:id',
        name: 'productDetails',
        component: () => import('@/views/products/ProductDetail.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: '/product-modifier-groups',
        name: 'modifiergroups',
        component: () =>
          import('@/views/products/ProductModifierGroup.vue'),
        meta: { requiresAuth: true }
      },
      // {
      //   path: '/suppliers',
      //   name: 'Suppliers',
      //   component: () => import('@/views/stocks/SupplierManagement.vue'),
      //   meta: { requiresAuth: true }
      // },
      // {
      //   path: '/stocks',
      //   name: 'Stocks',
      //   component: () => import('@/views/stocks/StockManagement.vue'),
      //   meta: { requiresAuth: true }
      // },
      // {
      //   path: '/purchases',
      //   name: 'Purchases',
      //   component: () => import('@/views/stocks/PurchaseManagement.vue'),
      //   meta: { requiresAuth: true }
      // },
      // {
      //   path: '/purchase/create',
      //   name: 'PurchaseCreate',
      //   component: () => import('@/components/PurchaseForm.vue')
      // },
      // {
      //   path: '/purchase/:id/edit',
      //   name: 'PurchaseEdit',
      //   component: () => import('@/components/PurchaseForm.vue')
      // },
      // {
      //   path: '/purchases/:id/details',
      //   name: 'purchase-details',
      //   component: () => import('@/views/purchases/PurchaseDetails.vue')
      // },
      // {
      //   path: '/purchase-reports',
      //   name: 'Reports',
      //   component: () => import('@/views/reports/PurchaseReport.vue'),
      //   meta: { requiresAuth: true }
      // },
      // {
      //   path: '/inventory-reports',
      //   name: 'InventoryReport',
      //   component: () => import('@/views/reports/InventoryReport.vue'),
      //   meta: { requiresAuth: true }
      // },
      // {
      //   path: '/audit-logs',
      //   name: 'AuditLogs',
      //   component: () => import('@/views/auditLogs/AuditLogPage.vue'),
      //   meta: { requiresAuth: true }
      // },
      // {
      //   path: '/audit-log/:id',
      //   name: 'audit-log-details',
      //   component: () => import('@/views/auditLogs/AuditLogDetails.vue'),
      //   props: true
      // },
      // {
      //   path: '/sales-reports',
      //   name: 'Sales',
      //   component: () => import('@/views/reports/SaleReport.vue'),
      //   meta: { requiresAuth: true }
      // },
      // {
      //   path: '/menu-management',
      //   name: 'MenuManagement',
      //   component: () => import('@/views/menus/MenuManagement.vue'),
      //   meta: { requiresAuth: true }
      // },
      {
        path: '/menu-management',
        name: 'MenuManagement',
        component: () => import('@/views/catalogs/MenuManagement.vue'),
        meta: { requiresAuth: true }
      },
      // {
      //   path: '/notifications',
      //   name: 'Notifications',
      //   component: () => import('@/views/Notification.vue'),
      //   meta: { requiresAuth: true }
      // },
      // {
      //   path: '/settings/tax',
      //   name: 'TaxSettings',
      //   component: () => import('@/views/setting/SettingsTax.vue'),
      //   meta: { requiresAuth: true }
      // },
      // {
      //   path: '/settings/invoice-customization',
      //   name: 'InvoiceCustomization',
      //   component: () => import('@/views/setting/InvoiceCustomization.vue'),
      //   meta: { requiresAuth: true }
      // },
      // {
      //   path: '/expense-management',
      //   name: 'Expense',
      //   component: () => import('@/views/expenses/ExpenseManagement.vue'),
      //   meta: { requiresAuth: true }
      // },
      {
        path: '/staff-management',
        name: 'Staff',
        component: () => import('@/views/staff/StaffManagement.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: '/shift-assignments',
        name: 'ShiftAssignments',
        component: () => import('@/views/staff/ShiftAssignment.vue'),
        meta: { requiresAuth: true }
      },
      {
        path: '/shift-management',
        name: 'Shifts',
        component: () => import('@/views/staff/ShiftManagement.vue')
      },
      // {
      //   path: '/payroll',
      //   name: 'Payroll',
      //   component: () => import('@/views/staff/PayrollManagement.vue'),
      //   meta: { requiresAuth: true }
      // },
      // {
      //   path: '/attendance',
      //   name: 'Attendance',
      //   component: () => import('@/views/staff/StaffAttendance.vue'),
      //   meta: { requiresAuth: true }
      // },
      // {
      //   path: '/staff-performance',
      //   name: 'StaffPerformance',
      //   component: () => import('@/views/staff/StaffPerformance.vue'),
      //   meta: { requiresAuth: true }
      // },
      {
        path: '/dining-table',
        component: () => import('@/views/tables/TableManagement.vue')
      },
      {
        path: '/reservations',
        component: () => import('@/views/reservations/Reservation.vue'),
        meta: { requiresAuth: true }
      }
    ]
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// Global navigation guard
router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token')

  // Redirect logged-in users away from Login page
  if (to.name === 'Login' && token) {
    return next({ name: 'Dashboard' }) // or any protected route
  }

  // Redirect unauthenticated users from protected pages
  if (to.meta.requiresAuth && !token) {
    return next({ name: 'Login' })
  }

  next()
})

export default router
