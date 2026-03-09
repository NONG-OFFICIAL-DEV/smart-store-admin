<template>
  <v-container fluid class="pa-0">
    <!-- Breadcrumb -->
    <div class="d-flex align-center mb-5">
      <v-btn
        size="small"
        icon="mdi-arrow-left"
        variant="tonal"
        class="mr-3"
        @click="router.back()"
      />
      <div>
        <div class="d-flex align-center gap-1 text-caption text-grey mb-1">
          <span class="cursor-pointer" @click="router.push('/branches')">
            Branches
          </span>
          <v-icon icon="mdi-chevron-right" size="12" />
          <span>{{ store.branch?.name ?? 'Detail' }}</span>
        </div>
        <h2 class="text-h5 font-weight-bold">Branch Detail</h2>
      </div>
    </div>

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
      <v-card rounded="xl" elevation="0" border class="mb-5">
        <v-card-text class="pa-6">
          <div class="d-flex align-start justify-space-between flex-wrap gap-4">
            <div class="d-flex align-center gap-4">
              <v-avatar
                :color="typeColor(store.branch.type)"
                size="56"
                rounded="xl"
                variant="tonal"
              >
                <v-icon :icon="typeIcon(store.branch.type)" size="28" />
              </v-avatar>
              <div>
                <div class="d-flex align-center gap-2 mb-1">
                  <span class="text-h6 font-weight-bold">
                    {{ store.branch.name }}
                  </span>
                  <v-chip
                    :color="store.branch.is_open ? 'success' : 'error'"
                    size="x-small"
                    variant="tonal"
                  >
                    {{ store.branch.is_open ? 'Open' : 'Closed' }}
                  </v-chip>
                  <v-chip
                    :color="store.branch.is_active ? 'primary' : 'grey'"
                    size="x-small"
                    variant="tonal"
                  >
                    {{ store.branch.is_active ? 'Active' : 'Inactive' }}
                  </v-chip>
                </div>
                <div class="text-caption text-grey d-flex align-center gap-1">
                  <v-icon icon="mdi-map-marker-outline" size="14" />
                  {{
                    [
                      store.branch.address_line1,
                      store.branch.city,
                      store.branch.country
                    ]
                      .filter(Boolean)
                      .join(', ')
                  }}
                </div>
                <div
                  class="text-caption text-grey d-flex align-center gap-3 mt-1"
                >
                  <span v-if="store.branch.phone">
                    <v-icon icon="mdi-phone-outline" size="13" class="mr-1" />
                    {{ store.branch.phone }}
                  </span>
                  <span v-if="store.branch.email">
                    <v-icon icon="mdi-email-outline" size="13" class="mr-1" />
                    {{ store.branch.email }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Edit button -->
            <v-btn
              variant="tonal"
              prepend-icon="mdi-pencil"
              rounded="lg"
              @click="editDialog = true"
            >
              Edit Branch
            </v-btn>
          </div>
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

        <v-col cols="12" sm="4">
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
                @click="router.push('/menus')"
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
                @click="router.push('/staff')"
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

      <!-- ── Branch Settings ────────────────────────────────────────────── -->
      <v-card rounded="xl" elevation="0" border class="mt-5">
        <v-card-title class="pa-5 pb-3">
          <span class="text-body-1 font-weight-bold">Settings</span>
        </v-card-title>
        <v-card-text class="pt-0">
          <v-row dense>
            <v-col cols="6" sm="3">
              <div class="text-caption text-grey">Tax Rate</div>
              <div class="text-body-2 font-weight-medium">
                {{ store.branch.tax_rate }}%
              </div>
            </v-col>
            <v-col cols="6" sm="3">
              <div class="text-caption text-grey">Service Charge</div>
              <div class="text-body-2 font-weight-medium">
                {{ store.branch.service_charge_rate }}%
              </div>
            </v-col>
            <v-col cols="6" sm="3">
              <div class="text-caption text-grey">Type</div>
              <div class="text-body-2 font-weight-medium capitalize">
                {{ store.branch.type }}
              </div>
            </v-col>
            <v-col cols="6" sm="3">
              <div class="text-caption text-grey">Slug</div>
              <div class="text-body-2 font-weight-medium text-grey">
                {{ store.branch.slug }}
              </div>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>
    </template>
  </v-container>
</template>

<script setup>
  import { onMounted, ref } from 'vue'
  import { useRouter, useRoute } from 'vue-router'
  import { useBranchStore } from '@/stores/branchStore'

  const router = useRouter()
  const route = useRoute()
  const store = useBranchStore()

  const editDialog = ref(false)

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
  .capitalize {
    text-transform: capitalize;
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
