<template>
  <v-container fluid class="pa-0">
    <!-- Breadcrumb -->
    <AppPageHeader
      title="Branch Detail"
      show-back
      :breadcrumbs="[
        { title: 'Branches', to: '/branches' },
        { title: store.branch?.name ?? 'Detail' }
      ]"
    >
      <template #title-after>
        <v-chip
          v-if="store.branch?.is_active"
          color="success"
          size="x-small"
          variant="flat"
        >
          Active
        </v-chip>
      </template>

      <template #right>
        <v-btn prepend-icon="mdi-pencil" rounded="lg" class="bg-primary">
          Edit Branch
        </v-btn>
      </template>
    </AppPageHeader>

    <!-- Loading -->
    <div v-if="store.loading" class="d-flex justify-center py-16">
      <v-progress-circular indeterminate color="primary" size="48" />
    </div>

    <!-- Error -->
    <v-alert
      v-else-if="store.error"
      type="error"
      variant="tonal"
      :text="store.error"
      class="mb-4"
    />

    <template v-else-if="store.branch">
      <!-- ── Branch Header Card ──────────────────────────────────────────── -->
      <v-card rounded="xl" elevation="0" border class="mb-6 overflow-hidden">
        <v-card-text class="pa-0">
          <v-row no-gutters>
            <v-col cols="12" md="7" class="pa-6">
              <div class="d-flex align-center gap-4">
                <v-avatar
                  :color="typeColor(store.branch.type)"
                  size="64"
                  rounded="xl"
                  variant="tonal"
                  class="elevation-0"
                >
                  <v-icon :icon="typeIcon(store.branch.type)" size="32" />
                </v-avatar>

                <div class="flex-1 min-w-0">
                  <div class="d-flex align-center flex-wrap gap-2 mb-1">
                    <span class="text-h6 font-weight-black text-truncate">
                      {{ store.branch.name }}
                    </span>
                    <div class="d-flex gap-1">
                      <v-chip
                        :color="store.branch.is_open ? 'success' : 'error'"
                        size="x-small"
                        variant="flat"
                        class="font-weight-bold"
                      >
                        {{ store.branch.is_open ? 'OPEN' : 'CLOSED' }}
                      </v-chip>
                      <v-chip
                        :color="store.branch.is_active ? 'primary' : 'grey'"
                        size="x-small"
                        variant="tonal"
                        class="font-weight-bold"
                      >
                        {{ store.branch.is_active ? 'ACTIVE' : 'INACTIVE' }}
                      </v-chip>
                    </div>
                  </div>

                  <div
                    class="text-body-2 text-medium-emphasis d-flex align-center gap-1 mb-1"
                  >
                    <v-icon
                      icon="mdi-map-marker-outline"
                      size="16"
                      color="primary"
                    />
                    {{ fullAddress }}
                  </div>

                  <div class="d-flex align-center gap-4 mt-2">
                    <div
                      v-if="store.branch.phone"
                      class="text-caption text-grey-darken-1 d-flex align-center"
                    >
                      <v-icon icon="mdi-phone-outline" size="14" class="mr-1" />
                      {{ store.branch.phone }}
                    </div>
                    <div
                      v-if="store.branch.email"
                      class="text-caption text-grey-darken-1 d-flex align-center"
                    >
                      <v-icon icon="mdi-email-outline" size="14" class="mr-1" />
                      {{ store.branch.email }}
                    </div>
                  </div>
                </div>
              </div>
            </v-col>

            <v-divider vertical class="d-none d-md-block" />
            <v-divider class="d-md-none" />

            <v-col cols="12" md="5" class="pa-6 bg-grey-lighten-5">
              <div class="text-overline font-weight-black text-primary mb-4">
                Branch Settings
              </div>

              <v-row dense>
                <v-col cols="6">
                  <div class="setting-item">
                    <div class="text-tiny text-medium-emphasis">Tax Rate</div>
                    <div class="text-body-1 font-weight-black">
                      {{ store.branch.tax_rate }}%
                    </div>
                  </div>
                </v-col>
                <v-col cols="6">
                  <div class="setting-item">
                    <div class="text-tiny text-medium-emphasis">
                      Srv. Charge
                    </div>
                    <div class="text-body-1 font-weight-black">
                      {{ store.branch.service_charge_rate }}%
                    </div>
                  </div>
                </v-col>
                <v-col cols="6" class="mt-2">
                  <div class="setting-item">
                    <div class="text-tiny text-medium-emphasis">Type</div>
                    <div class="text-body-2 text-capitalize">
                      {{ store.branch.type }}
                    </div>
                  </div>
                </v-col>
                <v-col cols="6" class="mt-2">
                  <div class="setting-item">
                    <div class="text-tiny text-medium-emphasis">Slug</div>
                    <div
                      class="text-body-2 font-weight-medium text-grey-darken-1"
                    >
                      /{{ store.branch.slug }}
                    </div>
                  </div>
                </v-col>
              </v-row>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <!-- ── Quick Actions ──────────────────────────────────────────────── -->
      <v-row class="mb-5" dense>
        <v-col cols="12" sm="4">
          <v-card
            rounded="xl"
            elevation="0"
            border
            class="pa-4 d-flex align-center gap-3 cursor-pointer quick-action-card"
            :href="`/pos/${store.branch.slug}`"
            target="_blank"
          >
            <v-avatar color="primary" variant="tonal" size="44" rounded="lg">
              <v-icon icon="mdi-cash-register" size="22" />
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-bold">Open POS</div>
              <div class="text-caption text-grey">Point of Sale</div>
            </div>
            <v-spacer />
            <v-icon icon="mdi-open-in-new" size="16" color="grey" />
          </v-card>
        </v-col>
        <v-col cols="12" sm="4" v-if="store.branch.type == 'restaurant'">
          <v-card
            rounded="xl"
            elevation="0"
            border
            class="pa-4 d-flex align-center gap-3 cursor-pointer quick-action-card"
            :href="`/kds/${store.branch.slug}`"
            target="_blank"
          >
            <v-avatar color="orange" variant="tonal" size="44" rounded="lg">
              <v-icon icon="mdi-chef-hat" size="22" />
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-bold">Open KDS</div>
              <div class="text-caption text-grey">Kitchen Display</div>
            </div>
            <v-spacer />
            <v-icon icon="mdi-open-in-new" size="16" color="grey" />
          </v-card>
        </v-col>

        <v-col cols="12" sm="4">
          <v-card
            rounded="xl"
            elevation="0"
            border
            class="pa-4 d-flex align-center gap-3 cursor-pointer quick-action-card"
            :href="`/menu/${store.branch.slug}`"
            target="_blank"
          >
            <v-avatar color="success" variant="tonal" size="44" rounded="lg">
              <v-icon icon="mdi-qrcode" size="22" />
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-bold">Digital Menu</div>
              <div class="text-caption text-grey">Customer view</div>
            </div>
            <v-spacer />
            <v-icon icon="mdi-open-in-new" size="16" color="grey" />
          </v-card>
        </v-col>
      </v-row>

      <!-- ── Stats Row ──────────────────────────────────────────────────── -->
      <v-row class="mb-5" dense>
        <v-col cols="6" sm="3">
          <v-card rounded="xl" elevation="0" border class="pa-4 text-center">
            <div class="text-h5 font-weight-black text-primary">
              {{ store.stats?.orders_today ?? 0 }}
            </div>
            <div class="text-caption text-grey mt-1">Orders Today</div>
          </v-card>
        </v-col>
        <v-col cols="6" sm="3">
          <v-card rounded="xl" elevation="0" border class="pa-4 text-center">
            <div class="text-h5 font-weight-black text-success">
              ${{ store.stats?.revenue_today ?? '0.00' }}
            </div>
            <div class="text-caption text-grey mt-1">Revenue Today</div>
          </v-card>
        </v-col>
        <v-col cols="6" sm="3">
          <v-card rounded="xl" elevation="0" border class="pa-4 text-center">
            <div class="text-h5 font-weight-black text-warning">
              ${{ store.stats?.avg_order ?? '0.00' }}
            </div>
            <div class="text-caption text-grey mt-1">Avg Order</div>
          </v-card>
        </v-col>
        <v-col cols="6" sm="3">
          <v-card rounded="xl" elevation="0" border class="pa-4 text-center">
            <div class="text-h5 font-weight-black">
              {{ store.tableSummary?.total ?? 0 }}
            </div>
            <div class="text-caption text-grey mt-1">Total Tables</div>
          </v-card>
        </v-col>
      </v-row>

      <!-- ── Bottom Row ─────────────────────────────────────────────────── -->
      <v-row dense>
        <!-- Tables -->
        <v-col cols="12" md="4">
          <v-card rounded="xl" elevation="0" border height="100%">
            <v-card-title
              class="pa-5 pb-3 d-flex align-center justify-space-between"
            >
              <span class="text-body-1 font-weight-bold">Tables</span>
              <v-btn
                size="x-small"
                variant="tonal"
                rounded="lg"
                @click="router.push(`/branches/${route.params.id}/tables`)"
              >
                Manage
              </v-btn>
            </v-card-title>
            <v-card-text class="pt-0">
              <div class="d-flex justify-space-around text-center py-2">
                <div>
                  <div class="text-h6 font-weight-bold text-success">
                    {{ store.tableSummary?.available ?? 0 }}
                  </div>
                  <div class="text-caption text-grey">Available</div>
                </div>
                <v-divider vertical />
                <div>
                  <div class="text-h6 font-weight-bold text-error">
                    {{ store.tableSummary?.occupied ?? 0 }}
                  </div>
                  <div class="text-caption text-grey">Occupied</div>
                </div>
                <v-divider vertical />
                <div>
                  <div class="text-h6 font-weight-bold text-warning">
                    {{ store.tableSummary?.reserved ?? 0 }}
                  </div>
                  <div class="text-caption text-grey">Reserved</div>
                </div>
              </div>

              <!-- Table list -->
              <v-list density="compact" class="mt-2">
                <v-list-item
                  v-for="table in store.tables.slice(0, 5)"
                  :key="table.id"
                  rounded="lg"
                  class="px-2"
                >
                  <template #prepend>
                    <v-icon
                      :icon="
                        table.shape === 'circle'
                          ? 'mdi-circle-outline'
                          : 'mdi-rectangle-outline'
                      "
                      size="16"
                      class="mr-2"
                    />
                  </template>
                  <v-list-item-title class="text-body-2">
                    Table {{ table.table_number }}
                  </v-list-item-title>
                  <v-list-item-subtitle class="text-caption">
                    Capacity: {{ table.capacity }}
                  </v-list-item-subtitle>
                  <template #append>
                    <v-chip
                      :color="tableStatusColor(table.status)"
                      size="x-small"
                      variant="tonal"
                    >
                      {{ table.status }}
                    </v-chip>
                  </template>
                </v-list-item>
                <v-list-item
                  v-if="store.tables.length > 5"
                  class="text-caption text-grey text-center"
                >
                  +{{ store.tables.length - 5 }} more tables
                </v-list-item>
              </v-list>
            </v-card-text>
          </v-card>
        </v-col>

        <!-- Menus -->
        <v-col cols="12" md="4">
          <v-card rounded="xl" elevation="0" border height="100%">
            <v-card-title
              class="pa-5 pb-3 d-flex align-center justify-space-between"
            >
              <span class="text-body-1 font-weight-bold">Assigned Menus</span>
              <v-btn
                size="x-small"
                variant="tonal"
                rounded="lg"
                @click="router.push('/branch-menus')"
              >
                Manage
              </v-btn>
            </v-card-title>
            <v-card-text class="pt-0">
              <v-list density="compact">
                <v-list-item
                  v-for="menu in store.menus"
                  :key="menu.id"
                  rounded="lg"
                  class="px-2"
                >
                  <template #prepend>
                    <v-avatar
                      color="primary"
                      variant="tonal"
                      size="32"
                      rounded="lg"
                      class="mr-2"
                    >
                      <v-icon icon="mdi-book-open-outline" size="16" />
                    </v-avatar>
                  </template>
                  <v-list-item-title class="text-body-2">
                    {{ menu.name }}
                  </v-list-item-title>
                  <v-list-item-subtitle class="text-caption">
                    <span v-if="menu.pivot?.available_from">
                      {{ menu.pivot.available_from }} –
                      {{ menu.pivot.available_until }}
                    </span>
                    <span v-else>Always available</span>
                  </v-list-item-subtitle>
                  <template #append>
                    <v-chip
                      v-if="menu.is_default"
                      color="primary"
                      size="x-small"
                      variant="tonal"
                    >
                      Default
                    </v-chip>
                  </template>
                </v-list-item>
                <v-list-item
                  v-if="store.menus.length === 0"
                  class="text-caption text-grey"
                >
                  No menus assigned
                </v-list-item>
              </v-list>
            </v-card-text>
          </v-card>
        </v-col>

        <!-- Staff -->
        <v-col cols="12" md="4">
          <v-card rounded="xl" elevation="0" border height="100%">
            <v-card-title
              class="pa-5 pb-3 d-flex align-center justify-space-between"
            >
              <span class="text-body-1 font-weight-bold">
                Staff ({{ store.staff.length }})
              </span>
              <v-btn
                size="x-small"
                variant="tonal"
                rounded="lg"
                @click="router.push('/staff-management')"
              >
                Manage
              </v-btn>
            </v-card-title>
            <v-card-text class="pt-0">
              <v-list density="compact">
                <v-list-item
                  v-for="s in store.staff.slice(0, 6)"
                  :key="s.id"
                  rounded="lg"
                  class="px-2"
                >
                  <template #prepend>
                    <v-avatar
                      color="secondary"
                      variant="tonal"
                      size="32"
                      rounded="lg"
                      class="mr-2"
                    >
                      <span class="text-caption font-weight-bold">
                        {{ s.user?.first_name?.[0]
                        }}{{ s.user?.last_name?.[0] }}
                      </span>
                    </v-avatar>
                  </template>
                  <v-list-item-title class="text-body-2">
                    {{ s.user?.first_name }} {{ s.user?.last_name }}
                  </v-list-item-title>
                  <v-list-item-subtitle class="text-caption">
                    {{ s.role?.name ?? 'No role' }}
                  </v-list-item-subtitle>
                  <template #append>
                    <v-chip
                      :color="s.is_active ? 'success' : 'grey'"
                      size="x-small"
                      variant="tonal"
                    >
                      {{ s.is_active ? 'Active' : 'Inactive' }}
                    </v-chip>
                  </template>
                </v-list-item>
                <v-list-item
                  v-if="store.staff.length === 0"
                  class="text-caption text-grey"
                >
                  No staff assigned
                </v-list-item>
              </v-list>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </template>
  </v-container>
</template>

<script setup>
  import { onMounted, ref, computed } from 'vue'
  import { useRouter, useRoute } from 'vue-router'
  import { useBranchStore } from '@/stores/branchStore'
  import AppPageHeader from '@/components/customs/AppPageHeader.vue'

  const router = useRouter()
  const route = useRoute()
  const store = useBranchStore()

  const editDialog = ref(false)

  const fullAddress = computed(() => {
    return [store.branch.address_line1, store.branch.city, store.branch.country]
      .filter(Boolean)
      .join(', ')
  })
  // ── Helpers ────────────────────────────────────────────────────────────────────
  const typeIcon = type =>
    ({
      restaurant: 'mdi-silverware-fork-knife',
      cafe: 'mdi-coffee',
      kiosk: 'mdi-store-outline',
      food_truck: 'mdi-truck-outline'
    })[type] ?? 'mdi-store'

  const typeColor = type =>
    ({
      restaurant: 'primary',
      cafe: 'brown',
      kiosk: 'orange',
      food_truck: 'teal'
    })[type] ?? 'grey'

  const tableStatusColor = status =>
    ({
      available: 'success',
      occupied: 'error',
      reserved: 'warning'
    })[status] ?? 'grey'

  // ── Init ───────────────────────────────────────────────────────────────────────
  onMounted(async () => {
    await store.fetchBranchById(route.params.id)
  })
</script>

<style scoped>
  .cursor-pointer {
    cursor: pointer;
  }
  .quick-action-card {
    cursor: pointer;
    transition: box-shadow 0.2s;
    text-decoration: none;
    color: inherit;
  }
  .quick-action-card:hover {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
  }
  .gap-1 {
    gap: 4px;
  }
  .gap-2 {
    gap: 8px;
  }
  .gap-3 {
    gap: 12px;
  }
  .gap-4 {
    gap: 16px;
  }
</style>
