<script setup>
  import { ref, computed } from 'vue'
  import logo from '/logo.png'
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()

  const props = defineProps({
    user: Object, // user will be passed from parent (Layout.vue),
    rail: Boolean
  })

  // const rail = ref(false)
  const open = ref(['dashboard'])

  const filteredMenu = computed(() => {
    // if (!props.user) return []

    return menu.value
    // .map(link => {
    //   const roleOk = !link.roles || link.roles.includes(props.user.role_id)

    //   if (!roleOk) return null

    //   if (link.subLinks) {
    //     const filteredSubs = link.subLinks.filter(
    //       s => !s.roles || s.roles.includes(props.user.role_id)
    //     )
    //     return { ...link, subLinks: filteredSubs }
    //   }

    //   return link
    // })
    // .filter(Boolean)
  })

  const menu = computed(() => [
    {
      path: '/dashboard',
      title: t('menu.dashboard'),
      icon: 'mdi-view-dashboard',
      roles: [1, 2, 3]
    },
    {
      path: '/tenants',
      title: 'Tenants',
      icon: 'mdi-account-tie',
      roles: [1, 2, 3]
    },
    {
      path: '/branches',
      title: 'Branches',
      icon: 'mdi-domain',
      roles: [1, 2, 3]
    },
    {
      title: 'Operations',
      icon: 'mdi-silverware-fork-knife',
      roles: [1, 2],
      subLinks: [
        {
          path: '/pos',
          title: 'Point of Sale',
          icon: 'mdi-cash-register',
          roles: [1, 2],
          newTab: true
        },
        {
          path: '/kitchen-kds',
          title: 'Kitchen Display',
          icon: 'mdi-chef-hat',
          roles: [1, 2],
          newTab: true
        },
        {
          path: '/mobile-menu',
          title: 'Digital Menu',
          icon: 'mdi-cellphone-play',
          roles: [1, 2],
          newTab: true
        }
      ]
    },
    {
      title: 'Catalog', // Products and Menu management
      icon: 'mdi-food-apple',
      roles: [1, 2],
      subLinks: [
        {
          path: '/categories',
          title: 'Categories',
          icon: 'mdi-shape-outline',
          roles: [1]
        },
        {
          path: '/menu-management',
          title: 'Menu',
          icon: 'mdi-book-open-variant',
          roles: [1]
        },
        {
          path: '/branch-menus',
          title: 'Branch menus',
          icon: 'mdi-book-open-variant',
          roles: [1]
        },
        {
          path: '/dining-table',
          title: 'Dining table',
          icon: 'mdi-table-chair',
          roles: [1]
        },
        {
          path: '/products',
          title: 'Product List',
          icon: 'mdi-package-variant',
          roles: [1, 2]
        },
        {
          path: '/product-modifier-groups',
          title: 'Product Modifier',
          icon: 'mdi-layers-outline',
          roles: [1, 2]
        }
      ]
    },
    {
      title: 'Inventory', // Logistics and Stock
      icon: 'mdi-warehouse',
      roles: [1, 2, 3],
      subLinks: [
        {
          path: '/stocks',
          title: 'Current Stock',
          icon: 'mdi-stack-overflow',
          roles: [1, 2, 3]
        },
        {
          path: '/purchases',
          title: 'Purchases',
          icon: 'mdi-cart-arrow-down',
          roles: [1, 2, 3]
        },
        {
          path: '/suppliers',
          title: 'Suppliers',
          icon: 'mdi-truck-delivery',
          roles: [1]
        }
      ]
    },
    {
      title: 'Accounting', // Expenses and Sales Reports
      icon: 'mdi-calculator',
      roles: [1, 2, 3],
      subLinks: [
        {
          path: '/expense-management',
          title: 'Expense Tracker',
          icon: 'mdi-cash-minus',
          roles: [1, 2, 3]
        },
        {
          path: '/sales-reports',
          title: 'Sales Analytics',
          icon: 'mdi-chart-bar',
          roles: [1, 2, 3]
        },
        {
          path: '/purchase-reports',
          title: 'Purchase Reports',
          icon: 'mdi-file-document-outline',
          roles: [1, 2, 3]
        },
        {
          path: '/inventory-reports',
          title: 'Stock Reports',
          icon: 'mdi-clipboard-list',
          roles: [1, 2]
        }
      ]
    },
    /* --- NEW SECTION: HUMAN RESOURCES --- */
    {
      title: 'Staff & Payroll',
      icon: 'mdi-account-tie',
      roles: [1], // Usually only for Owners/Main Managers
      subLinks: [
        {
          path: '/staff-management',
          title: 'Staff List',
          icon: 'mdi-account-multiple',
          roles: [1]
        },
        {
          path: '/shift-management',
          title: 'Staff Shifts',
          icon: 'mdi-calendar-clock',
          roles: [1]
        },
        {
          title: 'Shift Assignments',
          icon: 'mdi-calendar-account-outline',
          path: '/shift-assignments'
        },
        {
          path: '/attendance',
          title: 'Attendance',
          icon: 'mdi-clock-check-outline',
          roles: [1, 2]
        },
        {
          path: '/payroll',
          title: 'Payroll & Salaries',
          icon: 'mdi-cash',
          roles: [1]
        },
        {
          path: '/staff-performance',
          title: 'Performance',
          icon: 'mdi-star-face',
          roles: [1]
        }
      ]
    },
    {
      title: 'System Admin',
      icon: 'mdi-cog-outline',
      roles: [1],
      subLinks: [
        {
          path: '/users-management',
          title: 'User Accounts',
          icon: 'mdi-account-group',
          roles: [1]
        },
        {
          path: '/roles-management',
          title: 'Role Access',
          icon: 'mdi-shield-lock',
          roles: [1]
        },
        {
          path: '/role-permissions',
          title: 'Access Permissions',
          icon: 'mdi-shield-lock',
          roles: [1]
        },
        {
          path: '/settings/tax',
          title: 'Tax & Business Info',
          icon: 'mdi-percent',
          roles: [1]
        },
        {
          path: '/settings/invoice-customization',
          title: 'Invoice Customization',
          icon: 'mdi-invoice-outline',
          roles: [1]
        },
        {
          path: '/audit-logs',
          title: 'Activity Logs',
          icon: 'mdi-history',
          roles: [1]
        }
      ]
    }
  ])
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
        :src="logo"
        :width="rail ? 32 : 140"
        contain
        transition="scale-transition"
      />
    </div>

    <!-- MENU -->
    <v-list v-model:opened="open" density="compact" class="sidebar-list">
      <div v-for="link in filteredMenu" :key="link.title">
        <!-- ===== SINGLE ITEM ===== -->
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

        <!-- ===== GROUP ===== -->
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
          <template v-for="sublink in link.subLinks" :key="sublink.title">
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
            />
          </template>
        </v-list-group>
      </div>
    </v-list>

    <!-- FOOTER -->
    <template #append>
      <div class="border-top bg-grey-lighten-5">
        <div v-if="!rail" class="text-center">
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
              ></v-list-item>
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

  /* Active State indicator (Vertical bar) */
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

  /* Custom Module Selector Styling */
  .module-selector :deep(.v-field__input) {
    font-size: 0.75rem !important;
    font-weight: 700;
    text-transform: uppercase;
  }
</style>
