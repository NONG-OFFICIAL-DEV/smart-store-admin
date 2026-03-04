<template>
  <custom-title
    icon="mdi-table"
    title="Tables"
    subtitle="Floor plan · Status · Capacity"
  >
    <template #right>
      <div class="d-flex gap-2">
        <v-btn-toggle
          v-model="viewMode"
          color="primary"
          mandatory
          density="compact"
        >
          <v-btn value="map" icon="mdi-floor-plan" />
          <v-btn value="list" icon="mdi-format-list-bulleted" />
        </v-btn-toggle>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          rounded="lg"
          @click="openCreate"
        >
          Add Table
        </v-btn>
      </div>
    </template>
  </custom-title>

  <v-container fluid class="pa-0">
    <!-- Stats -->
    <v-row class="mb-5">
      <v-col v-for="(stat, i) in stats" :key="i" cols="6" sm="3">
        <v-card
          rounded="xl"
          border
          elevation="0"
          class="pa-4 d-flex align-center gap-3"
        >
          <v-avatar :color="stat.color" variant="tonal" rounded="lg" size="44">
            <v-icon :icon="stat.icon" size="20" />
          </v-avatar>
          <div>
            <div class="text-h6 font-weight-bold">{{ stat.value }}</div>
            <div class="text-caption text-grey">{{ stat.label }}</div>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Filter bar -->
    <v-row align="center" class="mb-4 px-1" dense>
      <v-col cols="12" sm="auto">
        <v-btn-toggle
          v-model="statusFilter"
          color="primary"
          variant="tonal"
          rounded="lg"
        >
          <v-btn value="" size="small" class="text-none px-3">All</v-btn>
          <v-btn value="available" size="small" class="text-none px-3">
            <v-icon icon="mdi-circle" size="10" color="success" class="mr-1" />
            Available
          </v-btn>
          <v-btn value="occupied" size="small" class="text-none px-3">
            <v-icon icon="mdi-circle" size="10" color="error" class="mr-1" />
            Occupied
          </v-btn>
          <v-btn value="reserved" size="small" class="text-none px-3">
            <v-icon icon="mdi-circle" size="10" color="warning" class="mr-1" />
            Reserved
          </v-btn>
          <v-btn value="cleaning" size="small" class="text-none px-3">
            <v-icon icon="mdi-circle" size="10" color="blue" class="mr-1" />
            Cleaning
          </v-btn>
        </v-btn-toggle>
      </v-col>
      <v-spacer />
      <v-col cols="12" sm="auto">
        <v-select
          v-model="selectedFloorPlan"
          :items="floorPlanOptions"
          item-value="id"
          item-title="name"
          label="Floor Plan"
          variant="outlined"
          density="compact"
          rounded="lg"
          hide-details
          max-width="200"
          prepend-inner-icon="mdi-floor-plan"
          clearable
        />
      </v-col>
    </v-row>

    <!-- ══ MAP VIEW ══════════════════════════════════════════════════════════ -->
    <template v-if="viewMode === 'map'">
      <v-card rounded="xl" border elevation="0" class="floor-map-card">
        <v-card-text class="pa-4">
          <!-- Legend -->
          <div class="d-flex gap-4 mb-4 flex-wrap">
            <div
              v-for="s in statusLegend"
              :key="s.value"
              class="d-flex align-center gap-1"
            >
              <div class="status-dot" :style="{ background: s.color }" />
              <span class="text-caption text-grey">{{ s.label }}</span>
            </div>
          </div>

          <!-- Floor canvas -->
          <div class="floor-canvas" ref="canvasRef">
            <div
              v-for="table in filteredTables"
              :key="table.id"
              class="table-token"
              :class="[
                `shape-${table.shape || 'square'}`,
                `status-${table.status}`,
                { 'table-inactive': !table.is_active }
              ]"
              :style="tablePosition(table)"
              @click="openStatusChange(table)"
            >
              <div class="table-number">{{ table.table_number }}</div>
              <div class="table-capacity">
                <v-icon icon="mdi-account" size="10" />
                {{ table.capacity }}
              </div>

              <!-- Context menu -->
              <div class="table-actions" @click.stop>
                <v-menu>
                  <template #activator="{ props }">
                    <v-btn
                      v-bind="props"
                      icon="mdi-dots-vertical"
                      size="small"
                      variant="text"
                      density="compact"
                    />
                  </template>
                  <v-list density="compact" rounded="lg" min-width="160">
                    <v-list-item
                      prepend-icon="mdi-qrcode"
                      color="primary"
                      @click="openQR(table)"
                    >
                      QR Code
                    </v-list-item>
                    <v-list-item
                      prepend-icon="mdi-pencil-outline"
                      @click="openEdit(table)"
                    >
                      Edit
                    </v-list-item>
                    <v-list-item
                      prepend-icon="mdi-swap-horizontal"
                      @click="openStatusChange(table)"
                    >
                      Change Status
                    </v-list-item>
                    <v-list-item
                      prepend-icon="mdi-calendar-plus"
                      @click="openReservation(table)"
                      color="primary"
                    >
                      Reserve
                    </v-list-item>
                    <v-divider />
                    <v-list-item
                      prepend-icon="mdi-delete-outline"
                      color="error"
                      @click="confirmDelete(table)"
                    >
                      Delete
                    </v-list-item>
                  </v-list>
                </v-menu>
              </div>
            </div>

            <!-- Empty floor -->
            <div v-if="!filteredTables.length" class="floor-empty">
              <v-icon
                icon="mdi-floor-plan"
                size="48"
                color="grey-lighten-1"
                class="mb-2"
              />
              <p class="text-body-2 text-grey">No tables on this floor</p>
            </div>
          </div>
        </v-card-text>
      </v-card>
    </template>

    <!-- ══ LIST VIEW ═════════════════════════════════════════════════════════ -->
    <template v-else>
      <v-card rounded="xl" border elevation="0">
        <v-data-table
          :headers="headers"
          :items="filteredTables"
          :loading="loading"
          item-value="id"
          rounded="xl"
          hover
        >
          <!-- Table number -->
          <template #item.table_number="{ item }">
            <div class="d-flex align-center gap-2">
              <div
                class="table-icon"
                :class="`shape-${item.shape || 'square'} mini`"
              >
                {{ item.table_number }}
              </div>
              <div>
                <div class="text-body-2 font-weight-medium">
                  Table {{ item.table_number }}
                </div>
                <div class="text-caption text-grey">{{ item.shape }}</div>
              </div>
            </div>
          </template>

          <!-- Capacity -->
          <template #item.capacity="{ item }">
            <div class="d-flex align-center gap-1">
              <v-icon icon="mdi-account-group-outline" size="16" color="grey" />
              <span class="text-body-2">{{ item.capacity }}</span>
            </div>
          </template>

          <!-- Floor plan -->
          <template #item.floor_plan="{ item }">
            <span class="text-body-2 text-grey">
              {{ item.floor_plan?.name || '—' }}
            </span>
          </template>

          <!-- Status -->
          <template #item.status="{ item }">
            <v-chip
              :color="statusColor(item.status)"
              size="small"
              variant="tonal"
              :prepend-icon="statusIcon(item.status)"
              class="cursor-pointer"
              @click="openStatusChange(item)"
            >
              {{ item.status }}
            </v-chip>
          </template>

          <!-- Active -->
          <template #item.is_active="{ item }">
            <v-chip
              :color="item.is_active ? 'success' : 'grey'"
              size="x-small"
              variant="tonal"
            >
              {{ item.is_active ? 'Active' : 'Inactive' }}
            </v-chip>
          </template>

          <!-- Actions -->
          <template #item.actions="{ item }">
            <div class="d-flex gap-1 justify-end">
              <v-btn
                icon="mdi-qrcode"
                size="small"
                variant="text"
                color="primary"
                @click="openQR(item)"
              />
              <v-btn
                icon="mdi-calendar-plus"
                size="small"
                variant="text"
                color="primary"
                @click="openReservation(item)"
              />
              <v-btn
                icon="mdi-pencil-outline"
                size="small"
                variant="text"
                @click="openEdit(item)"
              />
              <v-btn
                icon="mdi-delete-outline"
                size="small"
                variant="text"
                color="error"
                @click="confirmDelete(item)"
              />
            </div>
          </template>

          <template #no-data>
            <div class="text-center py-10">
              <v-icon
                icon="mdi-table-chair"
                size="48"
                color="grey-lighten-1"
                class="mb-2"
              />
              <p class="text-body-2 text-medium-emphasis">No tables found</p>
            </div>
          </template>
        </v-data-table>
      </v-card>
    </template>
  </v-container>

  <!-- Table Form Dialog -->
  <TableFormDialog
    v-model="dialog"
    :item="selectedItem"
    :floor-plans="floorPlanOptions"
    :loading="saving"
    @save="handleSave"
  />

  <!-- Status Change Dialog -->
  <v-dialog v-model="statusDialog" max-width="360">
    <v-card rounded="xl" elevation="0" border>
      <v-card-title class="pa-5 pb-4">
        <div class="text-h6 font-weight-bold">
          Table {{ statusTarget?.table_number }} — Change Status
        </div>
      </v-card-title>
      <v-divider />
      <v-card-text class="pa-4">
        <v-row dense>
          <v-col v-for="s in statusOptions" :key="s.value" cols="6">
            <v-btn
              block
              :color="s.color"
              :variant="statusTarget?.status === s.value ? 'flat' : 'tonal'"
              rounded="lg"
              class="text-none mb-2"
              :prepend-icon="s.icon"
              :loading="saving && newStatus === s.value"
              @click="changeStatus(s.value)"
            >
              {{ s.label }}
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>
  </v-dialog>

  <!-- Delete Confirm -->
  <v-dialog v-model="deleteDialog" max-width="400" persistent>
    <v-card rounded="xl" elevation="0" border>
      <v-card-text class="pa-6 text-center">
        <v-avatar color="error" size="56" rounded="lg" class="mb-4">
          <v-icon icon="mdi-delete-outline" size="28" />
        </v-avatar>
        <h3 class="text-h6 font-weight-bold mb-2">Delete Table?</h3>
        <p class="text-body-2 text-medium-emphasis">
          Table
          <strong>{{ deleteTarget?.table_number }}</strong>
          will be permanently removed.
        </p>
      </v-card-text>
      <v-card-actions class="px-6 pb-6 pt-0 gap-3">
        <v-btn block variant="tonal" rounded="lg" @click="deleteDialog = false">
          Cancel
        </v-btn>
        <v-btn
          block
          color="error"
          variant="flat"
          rounded="lg"
          :loading="saving"
          @click="handleDelete"
        >
          Delete
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- Snackbar -->
  <v-snackbar
    v-model="snackbar.show"
    :color="snackbar.color"
    location="bottom right"
    rounded="lg"
    :timeout="3000"
  >
    {{ snackbar.message }}
  </v-snackbar>
  <TableQRDialog
    v-model="qrDialog"
    :table="qrTarget"
    :branch-name="'Your Restaurant'"
  />
  <!-- :menu-base-url="`${$config?.appUrl || 'https://menu.yourapp.com'}/table/`" -->
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useRouter } from 'vue-router'
  import { useTableStore } from '@/stores/tableStore'
  import TableFormDialog from '@/components/tables/TableFormDialog.vue'
  import TableQRDialog from '@/components/tables/TableQRDialog.vue'

  const qrDialog = ref(false)
  const qrTarget = ref(null)
  const openQR = table => {
    qrTarget.value = table
    qrDialog.value = true
  }

  const router = useRouter()
  const tableStore = useTableStore()

  // ← store.tables, store.pagination
  const { tables, pagination } = storeToRefs(tableStore)

  const loading = ref(false)
  const saving = ref(false)
  const viewMode = ref('map')
  const statusFilter = ref('')
  const selectedFloorPlan = ref(null)
  const dialog = ref(false)
  const statusDialog = ref(false)
  const deleteDialog = ref(false)
  const selectedItem = ref(null)
  const statusTarget = ref(null)
  const deleteTarget = ref(null)
  const newStatus = ref(null)
  const snackbar = ref({ show: false, message: '', color: 'success' })

  const showSnack = (m, c = 'success') => {
    snackbar.value = { show: true, message: m, color: c }
  }

  // Mock floor plans — replace with store
  const floorPlanOptions = ref([{ id: null, name: 'All Floors' }])

  // Stats
  const stats = computed(() => [
    {
      label: 'Total Tables',
      value: tables.value.length,
      icon: 'mdi-table-chair',
      color: 'primary'
    },
    {
      label: 'Available',
      value: tables.value.filter(t => t.status === 'available').length,
      icon: 'mdi-check-circle-outline',
      color: 'success'
    },
    {
      label: 'Occupied',
      value: tables.value.filter(t => t.status === 'occupied').length,
      icon: 'mdi-account-group',
      color: 'error'
    },
    {
      label: 'Reserved',
      value: tables.value.filter(t => t.status === 'reserved').length,
      icon: 'mdi-calendar-clock',
      color: 'warning'
    }
  ])

  // Filter
  const filteredTables = computed(() => {
    let list = tables.value
    if (statusFilter.value)
      list = list.filter(t => t.status === statusFilter.value)
    if (selectedFloorPlan.value)
      list = list.filter(t => t.floor_plan_id === selectedFloorPlan.value)
    return list
  })

  // Table headers for list view
  const headers = [
    { title: 'Table', key: 'table_number', sortable: true },
    { title: 'Capacity', key: 'capacity', sortable: true },
    { title: 'Shape', key: 'shape', sortable: false },
    { title: 'Floor Plan', key: 'floor_plan', sortable: false },
    { title: 'Status', key: 'status', sortable: true },
    { title: 'Active', key: 'is_active', sortable: false },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ]

  // Status options for quick change
  const statusOptions = [
    {
      value: 'available',
      label: 'Available',
      color: 'success',
      icon: 'mdi-check-circle-outline'
    },
    {
      value: 'occupied',
      label: 'Occupied',
      color: 'error',
      icon: 'mdi-account-group'
    },
    {
      value: 'reserved',
      label: 'Reserved',
      color: 'warning',
      icon: 'mdi-calendar-clock'
    },
    { value: 'cleaning', label: 'Cleaning', color: 'blue', icon: 'mdi-broom' },
    {
      value: 'inactive',
      label: 'Inactive',
      color: 'grey',
      icon: 'mdi-minus-circle-outline'
    }
  ]

  const statusLegend = [
    { value: 'available', label: 'Available', color: '#4caf50' },
    { value: 'occupied', label: 'Occupied', color: '#f44336' },
    { value: 'reserved', label: 'Reserved', color: '#ff9800' },
    { value: 'cleaning', label: 'Cleaning', color: '#2196f3' },
    { value: 'inactive', label: 'Inactive', color: '#9e9e9e' }
  ]

  // ── Actions ───────────────────────────────────────────────────────────────────
  const openCreate = () => {
    selectedItem.value = null
    dialog.value = true
  }
  const openEdit = t => {
    selectedItem.value = { ...t }
    dialog.value = true
  }
  const confirmDelete = t => {
    deleteTarget.value = t
    deleteDialog.value = true
  }
  const openStatusChange = t => {
    statusTarget.value = t
    statusDialog.value = true
  }
  const openReservation = t =>
    router.push({ name: 'Reservations', query: { table_id: t.id } })

  const changeStatus = async status => {
    newStatus.value = status
    saving.value = true
    try {
      // store.updateTable replaces item in tables array
      await tableStore.updateTable(statusTarget.value.id, { status })
      statusDialog.value = false
      showSnack(`Table ${statusTarget.value.table_number} → ${status}`)
    } catch {
      showSnack('Failed to update status', 'error')
    } finally {
      saving.value = false
      newStatus.value = null
    }
  }

  const handleDelete = async () => {
    saving.value = true
    try {
      // store.deleteTable filters tables array itself
      await tableStore.deleteTable(deleteTarget.value.id)
      deleteDialog.value = false
      showSnack(`Table ${deleteTarget.value.table_number} deleted`)
    } catch {
      showSnack('Failed to delete', 'error')
    } finally {
      saving.value = false
    }
  }

  const handleSave = async payload => {
    saving.value = true
    try {
      if (payload.id) {
        // store.updateTable replaces item in tables array by index
        await tableStore.updateTable(payload.id, payload)
        showSnack('Table updated')
      } else {
        // store.createTable unshifts into tables array
        await tableStore.createTable(payload)
        showSnack('Table created')
      }
      dialog.value = false
    } catch {
      showSnack('Failed to save', 'error')
    } finally {
      saving.value = false
    }
  }

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const statusColor = s =>
    ({
      available: 'success',
      occupied: 'error',
      reserved: 'warning',
      cleaning: 'blue',
      inactive: 'grey'
    })[s] || 'grey'

  const statusIcon = s =>
    ({
      available: 'mdi-check-circle-outline',
      occupied: 'mdi-account-group',
      reserved: 'mdi-calendar-clock',
      cleaning: 'mdi-broom',
      inactive: 'mdi-minus-circle-outline'
    })[s] || 'mdi-help'

  // Position table token on canvas
  // Uses position_x/position_y from DB, falls back to grid layout
  const tablePosition = table => {
    if (table.position_x != null && table.position_y != null) {
      return {
        position: 'absolute',
        left: `${table.position_x}px`,
        top: `${table.position_y}px`
      }
    }
    return {} // grid layout fallback via CSS
  }

  // store.fetchTables sets store.tables (note: res.data.data.data in your store)
  onMounted(async () => {
    loading.value = true
    try {
      await tableStore.fetchTables()
    } finally {
      loading.value = false
    }
  })
</script>

<style scoped>
  /* ── Floor canvas ─────────────────────────────────────────────────────────── */
  .floor-map-card {
    min-height: 500px;
  }
  .floor-canvas {
    position: relative;
    min-height: 460px;
    background: radial-gradient(circle, #e0e0e0 1px, transparent 1px);
    background-size: 32px 32px;
    background-color: #fafafa;
    border-radius: 12px;
    border: 1px solid rgba(0, 0, 0, 0.06);
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    padding: 24px;
    align-content: flex-start;
  }
  .floor-empty {
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 300px;
  }

  /* ── Table tokens ─────────────────────────────────────────────────────────── */
  .table-token {
    position: relative;
    width: 80px;
    height: 80px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 2px solid transparent;
    user-select: none;
  }
  .table-token:hover {
    transform: scale(1.08);
    z-index: 10;
  }
  .table-token:hover {
    opacity: 1;
  }

  /* Shapes */
  .shape-round {
    border-radius: 50%;
  }
  .shape-square {
    border-radius: 8px;
  }
  .shape-rectangle {
    border-radius: 8px;
    width: 110px;
    height: 70px;
  }
  .shape-bar {
    border-radius: 4px;
    width: 120px;
    height: 50px;
  }

  /* Status colors */
  .status-available {
    background: #e8f5e9;
    border-color: #4caf50;
    color: #2e7d32;
  }
  .status-occupied {
    background: #ffebee;
    border-color: #f44336;
    color: #c62828;
  }
  .status-reserved {
    background: #fff3e0;
    border-color: #ff9800;
    color: #e65100;
  }
  .status-cleaning {
    background: #e3f2fd;
    border-color: #2196f3;
    color: #1565c0;
  }
  .status-inactive {
    background: #f5f5f5;
    border-color: #bdbdbd;
    color: #757575;
    opacity: 0.6;
  }
  .table-inactive {
    opacity: 0.4;
  }

  .table-number {
    font-size: 18px;
    font-weight: 700;
    line-height: 1;
  }
  .table-capacity {
    font-size: 10px;
    display: flex;
    align-items: center;
    gap: 2px;
    margin-top: 2px;
  }

  .table-actions {
    position: absolute;
    top: -8px;
    right: -8px;
    transition: opacity 0.15s;
    background: white;
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  }

  /* Mini icon for list view */
  .table-icon.mini {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    border-radius: 6px;
    background: rgb(var(--v-theme-primary), 0.1);
    color: rgb(var(--v-theme-primary));
  }
  .table-icon.mini.shape-round {
    border-radius: 50%;
  }

  /* Legend */
  .status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
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
  .cursor-pointer {
    cursor: pointer;
  }
</style>
