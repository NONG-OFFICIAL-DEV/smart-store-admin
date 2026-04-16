<script setup>
  import { ref, computed } from 'vue'
  import logo from '/logo.png'
  import { useI18n } from 'vue-i18n'
  import { useAuthStore } from '@/stores/authStore'

  const { t } = useI18n()
  const authStore = useAuthStore()

  defineProps({
    user: Object,
    rail: Boolean,
    logo_url: String
  })

  const open = ref(['dashboard'])

  const can = code => authStore.can(code)
  // ── Menu Definition ───────────────────────────────────────────────────────────
  const menu = computed(() => [
    // ── 1. DASHBOARD ────────────────────────────────────────────────────────────
    {
      path: '/dashboard',
      title: t('menu.dashboard'),
      icon: 'mdi-view-dashboard-outline',
      show: !authStore.isSuperAdmin
    },
    {
      path: '/admin-dashboard',
      title: t('menu.dashboard'),
      icon: 'mdi-view-dashboard-outline',
      show: authStore.isSuperAdmin
    },

    // ── 2. TENANTS & BRANCHES ───────────────────────────────────────────────────
    {
      path: '/tenants',
      title: t('menu.tenant'),
      icon: 'mdi-office-building-outline',
      show: authStore.isSuperAdmin
    },
    {
      path: '/branches',
      title: t('menu.branches'),
      icon: 'mdi-store-outline',
      show: can('branches.view')
    },
    {
      path: '/operation',
      title: t('menu.operation'),
      icon: 'mdi-cash-register'
    },
    // ── 4. CATALOG ──────────────────────────────────────────────────────────────
    {
      title: authStore.isSuperAdmin
        ? 'Catalog / Products'
        : authStore.isMart
          ? t('menu.products')
          : t('menu.catalog'),

      icon: authStore.isSuperAdmin
        ? 'mdi-book-open-page-variant-outline'
        : authStore.isMart
          ? 'mdi-tag-outline'
          : 'mdi-book-open-page-variant-outline',
      // show: can('categories.view') || can('products.view'),
      subLinks: [
        {
          path: '/categories',
          title: t('menu.categories'),
          icon: 'mdi-shape-outline'
          // show: can('categories.view')
        },
        {
          path: '/products',
          title: t('menu.products'),
          icon: 'mdi-tag-outline'
          // show: can('products.view')
        },
        {
          path: '/ingredients',
          title: 'Ingredients',
          icon: 'mdi-tree',
          show: authStore.isFood || authStore.isSuperAdmin
          // show: can('products.view')
        },
        {
          path: '/product-modifier-groups',
          title: 'Modifiers',
          icon: 'mdi-tune-variant',
          show: authStore.isFood || authStore.isSuperAdmin
          // show: can('products.view')
        },
        {
          path: '/menu-management',
          title: 'Menus',
          icon: 'mdi-menu',
          show: authStore.isFood || authStore.isSuperAdmin
          // show: can('products.view')
        },
        {
          path: '/branch-menus',
          title: 'Branch Menus',
          icon: 'mdi-book-open-variant',
          show: authStore.isFood || authStore.isSuperAdmin
          // show: can('products.view')
        }
      ]
    },

    // ── 5. DINING ───────────────────────────────────────────────────────────────
    {
      title: 'Dining',
      icon: 'mdi-silverware-fork-knife',
      // show: can('tables.view') || can('reservations.view'),
      show: authStore.isFood || authStore.isSuperAdmin,
      subLinks: [
        {
          path: '/dining-table',
          title: 'Tables',
          icon: 'mdi-table-chair'
          // show: can('tables.view')
        },
        {
          path: '/reservations',
          title: 'Reservations',
          icon: 'mdi-calendar-check-outline'
          // show: can('reservations.view')
        }
      ]
    },

    // ── 6. INVENTORY ────────────────────────────────────────────────────────────
    {
      title: t('menu.inventory'),
      icon: 'mdi-warehouse',
      // show: can('inventory.view'),
      subLinks: [
        {
          path: '/stocks',
          title: t('menu.stock_overview'),
          icon: 'mdi-layers-triple-outline',
          show: authStore.isFood || authStore.isSuperAdmin,
          badge: authStore.isSuperAdmin ? 'RS' : null
          // show: can('inventory.view')
        },
        {
          path: '/mart/stock',
          title: t('menu.stock_overview'),
          icon: 'mdi-layers-triple-outline',
          show: authStore.isMart || authStore.isSuperAdmin,
          badge: authStore.isSuperAdmin ? 'Mart' : null
          // show: can('inventory.view')
        },
        {
          path: '/purchases',
          title: t('menu.purchase_order'),
          icon: 'mdi-cart-arrow-down',
          show: authStore.isFood || authStore.isSuperAdmin,
          badge: authStore.isSuperAdmin ? 'RS' : null

          // show: can('inventory.view')
        },
        {
          path: '/mart/purchase-order',
          title: t('menu.purchase_order'),
          icon: 'mdi-cart-arrow-down',
          show: authStore.isMart || authStore.isSuperAdmin,
          badge: authStore.isSuperAdmin ? 'Mart' : null
          // show: can('inventory.view')
        },
        {
          path: '/suppliers',
          title: t('menu.suppliers'),
          icon: 'mdi-truck-delivery-outline'
          // show: can('inventory.view')
        }
      ]
    },

    // ── 7. ACCOUNTING ───────────────────────────────────────────────────────────
    {
      title: t('menu.reports'),
      icon: 'mdi-chart-areaspline',
      // show: can('reports.sales') || can('reports.inventory'),
      subLinks: [
        {
          show: authStore.isSuperAdmin,
          path: '/expense-management',
          title: 'Expenses',
          icon: 'mdi-cash-minus'
          // show: can('reports.sales')
        },
        {
          path: '/orders-reports',
          title: t('menu.orders_analytics'),
          icon: 'mdi-chart-bar'
          // show: can('reports.sales')
        },
        {
          path: '/mart/reports/purchases',
          title: t('menu.purchase_reports'),
          icon: 'mdi-file-document-outline'
          // show: can('reports.inventory')
        },
        {
          path: '/stock-reports',
          title: t('menu.stock_reports'),
          icon: 'mdi-clipboard-list-outline'
          // show: can('reports.inventory')
        }
      ]
    },

    // ── 8. STAFF & PAYROLL ──────────────────────────────────────────────────────
    {
      title: t('menu.staff'),
      icon: 'mdi-account-group-outline',
      // show: can('staff.view'),
      subLinks: [
        {
          path: '/staff-management',
          title: t('menu.staff_list'),
          icon: 'mdi-account-multiple-outline'
          // show: can('staff.view')
        },
        {
          path: '/shift-management',
          title: t('menu.shift'),
          icon: 'mdi-clock-outline'
          // show: can('staff.view')
        },
        {
          title: t('menu.shift_assign'),
          path: '/shift-assignments',
          icon: 'mdi-calendar-account-outline'
          // show: can('staff.view')
        },
        {
          path: '/attendance',
          title: 'Attendance',
          icon: 'mdi-clock-check-outline',
          show: authStore.isSuperAdmin
          // show: can('staff.view')
        },
        {
          path: '/payroll',
          title: 'Payroll',
          icon: 'mdi-cash-multiple',
          show: authStore.isSuperAdmin
          // show: can('staff.view')
        },
        {
          path: '/staff-performance',
          title: 'Performance',
          icon: 'mdi-chart-timeline-variant',
          show: authStore.isSuperAdmin
          // show: can('staff.view')
        }
      ]
    },

    // ── 9. SYSTEM ADMIN ─────────────────────────────────────────────────────────
    {
      title: 'System',
      icon: 'mdi-shield-crown-outline',
      show: authStore.isSuperAdmin,
      subLinks: [
        {
          path: '/users-management',
          title: 'User Accounts',
          icon: 'mdi-account-cog-outline'
          // show: can('settings.view')
        },
        {
          path: '/roles-management',
          title: 'Roles',
          icon: 'mdi-shield-account-outline'
          // show: can('roles.manage')
        },
        {
          path: '/role-permissions',
          title: 'Permissions',
          icon: 'mdi-lock-outline'
          // show: can('roles.manage')
        },
        {
          path: '/settings/invoice-customization',
          title: 'Invoice',
          icon: 'mdi-invoice-text-outline'
          // show: can('settings.edit')
        },
        {
          path: '/audit-logs',
          title: t('menu.activity_log'),
          icon: 'mdi-history'
          // show: can('settings.view')
        }
      ]
    }
  ])

  // ── Filtered Menu — removes hidden items and empty groups ─────────────────────
  const filteredMenu = computed(() =>
    menu.value
      .filter(item => item.show !== false)
      .map(item => {
        if (!item.subLinks) return item
        return {
          ...item,
          subLinks: item.subLinks.filter(s => s.show !== false)
        }
      })
      .filter(item => !item.subLinks || item.subLinks.length > 0)
  )
</script>

<template>
  <v-navigation-drawer
    :rail="rail"
    permanent
    elevation="0"
    class="border-right"
    width="260"
  >
    <!-- LOGO -->
    <div
      class="pa-4 d-flex justify-center align-center"
      :style="rail ? 'height: 64px' : 'height: 100px'"
    >
      <v-img
        :src="logo_url || logo"
        :width="rail ? 32 : 140"
        contain
        transition="scale-transition"
      />
    </div>

    <!-- MENU -->
    <v-list v-model:opened="open" density="compact" class="sidebar-list">
      <div v-for="link in filteredMenu" :key="link.title || link.path">
        <!-- ── SINGLE ITEM ───────────────────────────────────────────────── -->
        <template v-if="!link.subLinks">
          <v-tooltip v-if="rail" location="right">
            <template #activator="{ props }">
              <v-list-item
                v-bind="props"
                :to="!link.newTab ? link.path : undefined"
                :href="link.newTab ? link.path : undefined"
                :target="link.newTab ? '_blank' : undefined"
                :prepend-icon="link.icon"
                rounded="lg"
                class="mb-1"
                active-class="active-item"
              />
            </template>
            <span>{{ link.title }}</span>
          </v-tooltip>

          <v-list-item
            v-else
            :to="!link.newTab ? link.path : undefined"
            :href="link.newTab ? link.path : undefined"
            :target="link.newTab ? '_blank' : undefined"
            :prepend-icon="link.icon"
            :title="link.title"
            rounded="lg"
            class="mb-1"
            active-class="active-item"
          />
        </template>

        <!-- ── GROUP ────────────────────────────────────────────────────── -->
        <v-list-group v-else :value="link.title">
          <template #activator="{ props }">
            <v-tooltip v-if="rail" location="right">
              <template #activator="{ props: tooltipProps }">
                <v-list-item
                  v-bind="{ ...props, ...tooltipProps }"
                  :prepend-icon="link.icon"
                  rounded="lg"
                  class="mb-1"
                />
              </template>
              <span>{{ link.title }}</span>
            </v-tooltip>

            <v-list-item
              v-else
              v-bind="props"
              :prepend-icon="link.icon"
              :title="link.title"
              rounded="lg"
              class="mb-1"
            />
          </template>

          <!-- SUB ITEMS -->
          <template v-for="sublink in link.subLinks" :key="sublink.path">
            <v-tooltip v-if="rail" location="right">
              <template #activator="{ props }">
                <v-list-item
                  v-bind="props"
                  :to="!sublink.newTab ? sublink.path : undefined"
                  :href="sublink.newTab ? sublink.path : undefined"
                  :target="sublink.newTab ? '_blank' : undefined"
                  :prepend-icon="sublink.icon"
                  density="compact"
                  class="sub-item"
                  active-class="active-item"
                />
              </template>
              <span>{{ sublink.title }}</span>
            </v-tooltip>

            <v-list-item
              v-else
              :to="!sublink.newTab ? sublink.path : undefined"
              :href="sublink.newTab ? sublink.path : undefined"
              :target="sublink.newTab ? '_blank' : undefined"
              :prepend-icon="sublink.icon"
              :title="sublink.title"
              density="compact"
              class="sub-item"
              active-class="active-item"
            >
              <template #append>
                <v-chip
                  v-if="sublink.badge"
                  size="x-small"
                  :color="sublink.badge === 'Mart' ? 'indigo' : 'blue'"
                  variant="tonal"
                  rounded="lg"
                  class="ml-2"
                >
                  {{ sublink.badge }}
                </v-chip>
              </template>
            </v-list-item>
          </template>
        </v-list-group>
      </div>
    </v-list>

    <!-- FOOTER -->
    <template #append>
      <div class="border-top bg-grey-lighten-5">
        <div v-if="!rail" class="text-center pa-3">
          <p class="text-overline text-grey-darken-1 mb-1">POS System v0.0.1</p>
          <v-chip size="x-small" color="primary" variant="tonal">
            Stable Release
          </v-chip>
        </div>
        <v-list v-else>
          <v-tooltip location="right">
            <template #activator="{ props }">
              <v-list-item
                v-bind="props"
                prepend-icon="mdi-information-outline"
              />
            </template>
            <span>POS System v0.0.1</span>
          </v-tooltip>
        </v-list>
      </div>
    </template>
  </v-navigation-drawer>
</template>

<style scoped>
  :deep(.v-list-group__items .v-list-item) {
    padding-inline-start: 16px !important;
    background-color: rgb(233, 226, 226) !important;
  }

  .sub-item :deep(.v-list-item-title) {
    font-size: 0.825rem !important;
    opacity: 0.85;
  }

  .active-item {
    background: rgba(var(--v-theme-primary), 0.1) !important;
    color: rgb(var(--v-theme-primary)) !important;
    position: relative;
  }

  .active-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 20%;
    bottom: 20%;
    width: 4px;
    background: rgb(var(--v-theme-primary));
    border-radius: 0 4px 4px 0;
  }
</style>
