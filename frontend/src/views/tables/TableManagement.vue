<template>
  <v-container fluid class="pa-0">
    <AppToolbar :title="$t('menu.tables')" :subtitle="$t('tables.subtitle')">
      <template #actions>
        <v-btn
          v-if="mode === 'manage'"
          color="primary"
          prepend-icon="mdi-plus"
          rounded="lg"
          elevation="0"
          @click="openCreate"
        >
          {{ $t('tables.add') }}
        </v-btn>
        <v-btn-toggle
          v-model="mode"
          color="primary"
          variant="tonal"
          density="comfortable"
          rounded="lg"
          mandatory
        >
          <v-btn
            value="order"
            class="text-none"
            prepend-icon="mdi-silverware-fork-knife"
          >
            {{ $t('tables.mode.order') }}
          </v-btn>
          <v-btn
            value="manage"
            class="text-none"
            prepend-icon="mdi-cog-outline"
          >
            {{ $t('tables.mode.manage') }}
          </v-btn>
        </v-btn-toggle>
      </template>
    </AppToolbar>

    <MobileOrdersCarousel v-if="showMobileOrdersTables" :branch-id="branchId" class="mb-3" />

    <div class="d-flex align-center flex-wrap ga-1 mb-2">
      <span class="text-body-2">
        {{ t('tables.summary_count', { count: tableStats.total }) }}
      </span>
      <span class="text-body-2 text-medium-emphasis">·</span>
      <span class="text-body-2 text-warning font-weight-medium">
        {{ t('tables.summary_occupied', { count: tableStats.occupied }) }}
      </span>
      <template v-if="tableStats.occupied">
        <span class="text-body-2 text-medium-emphasis">·</span>
        <span class="text-body-2 font-weight-medium">
          {{ formatBase(tableStats.dueTotal) }} {{ t('tables.summary_due') }}
        </span>
      </template>
    </div>

    <div class="d-flex align-center flex-wrap ga-3 mb-4">
      <v-btn-toggle
        v-model="statusFilter"
        color="primary"
        variant="tonal"
        density="comfortable"
        rounded="lg"
        mandatory
      >
        <v-btn value="all" class="text-none">
          {{ t('tables.filter.all') }} {{ tableStats.total }}
        </v-btn>
        <v-btn value="occupied" class="text-none">
          {{ t('tables.filter.occupied') }} {{ tableStats.occupied }}
        </v-btn>
        <v-btn value="free" class="text-none">
          {{ t('tables.filter.free') }} {{ tableStats.free }}
        </v-btn>
      </v-btn-toggle>

      <v-text-field
        v-model="search"
        :placeholder="$t('btn.search')"
        variant="outlined"
        density="compact"
        rounded="lg"
        hide-details
        clearable
        prepend-inner-icon="mdi-magnify"
        style="max-width: 220px"
      />
    </div>

    <div v-if="loading" class="d-flex justify-center py-10">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <v-alert
      v-else-if="!filteredTables.length"
      type="info"
      variant="tonal"
      rounded="lg"
    >
      {{ $t('tables.empty') }}
    </v-alert>

    <template v-else>
      <v-alert
        v-if="mode === 'manage'"
        type="info"
        variant="tonal"
        density="compact"
        rounded="lg"
        class="mb-3"
      >
        {{ search ? $t('tables.reorder.clear_search_hint') : $t('tables.reorder.hint') }}
      </v-alert>

      <draggable
        v-model="manageTables"
        tag="div"
        :class="mode === 'order' ? 'tables-grid' : 'v-row v-row--dense'"
        item-key="id"
        handle=".table-drag-handle"
        :disabled="mode !== 'manage' || !!search || reordering"
        @end="onReorderEnd"
      >
        <template #item="{ element: table }">
          <div :class="mode === 'order' ? '' : 'v-col v-col-6 v-col-sm-4 v-col-md-3 v-col-lg-2'">
            <!-- ── Order mode — compact tap tile, one tap target, no extra buttons ── -->
            <div
              v-if="mode === 'order'"
              class="table-tile"
              :class="tileClass(table)"
              @click="onCardClick(table)"
            >
              <div class="table-tile__top">
                <span class="table-tile__number">{{ table.table_number }}</span>
                <span class="table-tile__dot" :class="`table-tile__dot--${tileState(table)}`" />
                <span class="text-truncate">{{ tileStateLabel(table) }}</span>
              </div>

              <div class="table-tile__middle">
                <template v-if="table.is_occupied">
                  {{ formatBase(table.open_session?.total ?? 0) }}
                </template>
                <template v-else-if="table.status === 'available'">
                  {{ t('tables.order.tap_to_open') }}
                </template>
                <template v-else>
                  {{ statusLabel(table.status) }}
                </template>
              </div>

              <div class="table-tile__bottom">
                <v-icon icon="mdi-account-group-outline" size="14" />
                <span v-if="table.is_occupied">
                  {{ t('tables.order.pax_orders', { pax: table.capacity, orders: table.open_session?.order_count ?? 0 }) }}
                </span>
                <span v-else>
                  {{ t('tables.order.seats', { count: table.capacity }) }}
                </span>
              </div>
            </div>

            <!-- ── Manage mode — unchanged: drag handle, status chip, edit/QR/delete ── -->
            <v-card
              v-else
              rounded="lg"
              elevation="0"
              border
              class="table-card"
              :class="`table-card--${table.status}`"
            >
              <v-icon
                v-if="!search"
                icon="mdi-drag"
                size="18"
                class="table-drag-handle"
              />

              <div class="pa-3 d-flex flex-column align-center text-center">
                <div class="text-body-2 font-weight-medium mt-2">
                  {{ $t('tables.table_prefix') }} {{ table.table_number }}
                </div>
                <div class="text-caption text-grey d-flex align-center ga-1 mb-2">
                  <v-icon icon="mdi-account-group-outline" size="14" />
                  {{ table.capacity }}
                </div>
                <v-chip
                  :color="statusColor(table.status)"
                  size="small"
                  variant="tonal"
                  :prepend-icon="statusIcon(table.status)"
                  class="cursor-pointer"
                  @click.stop="openStatusChange(table)"
                >
                  {{ statusLabel(table.status) }}
                </v-chip>
              </div>

              <v-divider />
              <div class="d-flex justify-center pa-1" @click.stop>
                <v-btn
                  icon="mdi-qrcode"
                  size="small"
                  variant="text"
                  color="primary"
                  @click="openQR(table)"
                />
                <v-btn
                  icon="mdi-pencil-outline"
                  size="small"
                  variant="text"
                  @click="openEdit(table)"
                />
                <v-btn
                  icon="mdi-delete-outline"
                  size="small"
                  variant="text"
                  color="error"
                  @click="confirmDelete(table)"
                />
              </div>
            </v-card>
          </div>
        </template>
      </draggable>
    </template>

    <!-- Table Form Dialog -->
    <TableFormDialog
      v-model="dialog"
      :item="selectedItem"
      :floor-plans="floorPlanOptions"
      :loading="saving"
      @save="handleSave"
    />

    <!-- Status Change Dialog -->
    <AppDialog
      v-model="statusDialog"
      :max-width="360"
      :title="
        $t('tables.status_dialog_title', { number: statusTarget?.table_number })
      "
    >
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
    </AppDialog>

    <TableQRDialog
      v-if="qrDialog"
      v-model="qrDialog"
      :table="qrTarget"
      :branch-name="$t('tables.default_branch_name')"
    />

    <!-- Always the same drawer whether the table has one order or several —
         it shows every open order for the table as its own compact block.
         Printing the bill is a no-op preview; paying opens PaymentDialog on
         top of it. -->
    <BillDrawer
      v-model="orderDialog"
      :orders="orderDialogOrders"
      :table="orderDialogTable"
      @pay="paymentDialog = true"
      @freed="onPaid"
    />

    <PaymentDialog
      v-model="paymentDialog"
      :orders="orderDialogOrders"
      :table="orderDialogTable"
      @paid="onPaid"
    />
  </v-container>
</template>

<script setup>
  import { ref, computed, watch, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRouter } from 'vue-router'
  import draggable from 'vuedraggable'
  import { useAuthStore } from '@/stores/authStore'
  import { useTableStore } from '@/stores/tableStore'
  import { useFloorPlanStore } from '@/stores/floorPlanStore'
  import { usePosStore } from '@/stores/posStore'
  import TableFormDialog from '@/components/tables/TableFormDialog.vue'
  import TableQRDialog from '@/components/tables/TableQRDialog.vue'
  import BillDrawer from '@/components/tables/BillDrawer.vue'
  import PaymentDialog from '@/components/tables/PaymentDialog.vue'
  import { useAppUtils, AppDialog } from '@nong-official-dev/core'
  import AppToolbar from '@/components/common/AppToolbar.vue'
  import MobileOrdersCarousel from '@/components/pos/MobileOrdersCarousel.vue'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  const { confirm, notif } = useAppUtils()
  const { t } = useI18n()
  const router = useRouter()
  const { formatBase } = useCurrency()

  const qrDialog = ref(false)
  const qrTarget = ref(null)
  const openQR = table => {
    qrTarget.value = table
    qrDialog.value = true
  }

  const authStore = useAuthStore()
  const tableStore = useTableStore()
  const floorPlanStore = useFloorPlanStore()
  const posStore = usePosStore()

  // Same pattern as OrdersManagement.vue/KitchenDisplay.vue/StaffManagement.vue
  // — tables are branch-scoped data (one branch_id per table), so this page
  // must follow the sidebar's active branch like everything else, not load
  // every branch's tables merged together.
  const branchId = computed(() => authStore.activeBranchId || authStore.branch_id)
  const showMobileOrdersTables = computed(() => authStore.posSettings?.show_mobile_menu_orders_tables !== false)

  // Order mode — tap a table to either view its active order (occupied) or
  // jump into Food POS with the table preselected (available). Manage mode
  // is the original create/edit/delete/QR workflow, unchanged.
  const mode = ref('order')
  const search = ref('')
  const loading = ref(false)
  // all | occupied | free — "free" means "not occupied" (reserved/cleaning/
  // inactive tables are lumped in with it too, since this app's table
  // model only really has two customer-facing states worth filtering on).
  const statusFilter = ref('all')

  const saving = ref(false)
  const dialog = ref(false)
  const statusDialog = ref(false)
  const selectedItem = ref(null)
  const statusTarget = ref(null)
  const newStatus = ref(null)

  const orderDialog = ref(false)
  const orderDialogOrders = ref([])
  const orderDialogTable = ref(null)
  const paymentDialog = ref(false)

  // Payment succeeded — close both the dialog and the drawer it was opened
  // on top of (the table is free now, nothing left to show) and refresh the
  // grid so the just-paid table flips back to Free.
  function onPaid() {
    paymentDialog.value = false
    orderDialog.value = false
    loadTables()
  }

  // Real floor plans only — used by the create/edit form's picker.
  const floorPlanOptions = computed(() => floorPlanStore.floorPlans)

  const filteredTables = computed(() => {
    // Always a fresh array, even with no filter active — the "all" branch
    // used to return tableStore.tables itself unchanged, so updating one
    // table's status (which replaces that one array index, not the array
    // reference) never produced a referentially-different value for the
    // watch() below to notice, and the Manage tab's chip silently never
    // re-rendered despite the store having the correct new status.
    let list = [...tableStore.tables]
    if (statusFilter.value === 'occupied') list = list.filter(table => table.is_occupied)
    else if (statusFilter.value === 'free') list = list.filter(table => !table.is_occupied)

    const q = search.value?.trim().toLowerCase()
    if (!q) return list
    return list.filter(table => String(table.table_number).toLowerCase().includes(q))
  })

  // Header summary line + filter tab counts — always computed off every
  // loaded table, independent of the current search/status filter.
  const tableStats = computed(() => {
    const occupiedTables = tableStore.tables.filter(table => table.is_occupied)
    return {
      total: tableStore.tables.length,
      occupied: occupiedTables.length,
      free: tableStore.tables.length - occupiedTables.length,
      dueTotal: occupiedTables.reduce((sum, table) => sum + Number(table.open_session?.total ?? 0), 0)
    }
  })

  // Order-mode tile state — distinct from the raw `status` column so a
  // reserved/cleaning/inactive table doesn't get mislabeled "Free" even
  // though it's filtered alongside free tables (see filteredTables above).
  function tileState(table) {
    if (table.is_occupied) return 'occupied'
    if (table.status === 'available') return 'free'
    return 'other'
  }

  function tileStateLabel(table) {
    if (table.is_occupied) return t('tables.order.occupied_label')
    if (table.status === 'available') return t('tables.order.free_label')
    return statusLabel(table.status)
  }

  function tileClass(table) {
    return `table-tile--${tileState(table)}`
  }

  // Draggable needs its own mutable array to reorder into — kept in sync
  // with filteredTables (status changes, pay/paid refreshes, etc.) whenever
  // it's not actively being dragged.
  const manageTables = ref([])
  watch(filteredTables, val => { manageTables.value = [...val] }, { immediate: true })

  const reordering = ref(false)

  // Drag-reorder is only meaningful on the unfiltered list — the backend
  // would happily resequence a filtered subset, but staff dragging a
  // search result past tables they can't see would be confusing. The
  // handle + :disabled in the template already stop drags in that case;
  // this is just the save step once a drop actually happens.
  async function onReorderEnd() {
    if (mode.value !== 'manage' || search.value) return
    reordering.value = true
    try {
      await tableStore.reorderTables(manageTables.value.map(tb => tb.id))
      notif(t('tables.reorder.saved'), { type: 'success' })
    } catch {
      notif(t('tables.reorder.failed'), { type: 'error' })
      await loadTables() // resync to the server's actual order
    } finally {
      reordering.value = false
    }
  }

  async function loadTables() {
    if (!branchId.value) return
    loading.value = true
    try {
      await tableStore.fetchTables({ perPage: -1, branch_id: branchId.value })
    } finally {
      loading.value = false
    }
  }

  // Status options for quick change
  const statusOptions = [
    {
      value: 'available',
      label: t('tables.status.available'),
      color: 'success',
      icon: 'mdi-check-circle-outline'
    },
    {
      value: 'occupied',
      label: t('tables.status.occupied'),
      color: 'error',
      icon: 'mdi-account-group'
    },
    {
      value: 'reserved',
      label: t('tables.status.reserved'),
      color: 'warning',
      icon: 'mdi-calendar-clock'
    },
    {
      value: 'cleaning',
      label: t('tables.status.cleaning'),
      color: 'blue',
      icon: 'mdi-broom'
    },
    {
      value: 'inactive',
      label: t('status.inactive'),
      color: 'grey',
      icon: 'mdi-minus-circle-outline'
    }
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

  function onCardClick(table) {
    if (mode.value !== 'order') return
    if (table.is_occupied) return viewTableOrder(table)
    if (table.status === 'available') return startOrderAtTable(table)
    openStatusChange(table)
  }

  async function viewTableOrder(table) {
    try {
      const orders = await tableStore.fetchOrdersForTable(table.id)
      if (!orders.length) {
        notif(t('tables.order.no_active_order'), { type: 'warning' })
        return
      }
      orderDialogOrders.value = orders
      orderDialogTable.value = table
      orderDialog.value = true
    } catch {
      notif(t('tables.order.load_failed'), { type: 'error' })
    }
  }

  function startOrderAtTable(table) {
    posStore.setOrderType('dine_in')
    posStore.setTable(table.id)
    router.push({ name: 'pos-food' })
  }

  const openStatusChange = t => {
    statusTarget.value = t
    statusDialog.value = true
  }
  const changeStatus = async status => {
    newStatus.value = status
    saving.value = true
    try {
      await tableStore.updateTableStatus(statusTarget.value.id, status)
      statusDialog.value = false
      notif(
        t('tables.messages.status_changed', {
          number: statusTarget.value.table_number,
          status: statusLabel(status)
        }),
        { type: 'success' }
      )
    } catch {
      notif(t('tables.messages.status_update_failed'), {
        type: 'error'
      })
    } finally {
      saving.value = false
      newStatus.value = null
    }
  }

  const confirmDelete = async data => {
    saving.value = true
    try {
      confirm({
        title: t('tables.confirm_delete.title'),
        message: t('tables.confirm_delete.message', {
          number: data.table_number
        }),
        options: { type: 'warning', width: 550 },
        agree: async () => {
          await tableStore.deleteTable(data.id)
          notif(t('tables.messages.deleted', { number: data.table_number }), {
            type: 'success'
          })
        },
        cancel: () => {}
      })
    } catch {
      notif(t('tables.messages.delete_failed'), {
        type: 'error'
      })
    } finally {
      saving.value = false
    }
  }

  const handleSave = async payload => {
    saving.value = true
    try {
      if (payload.id) {
        await tableStore.updateTable(payload.id, payload)
        notif(t('tables.messages.updated'), {
          type: 'success'
        })
      } else {
        await tableStore.createTable(payload)
        notif(t('tables.messages.created'), {
          type: 'success'
        })
      }
      dialog.value = false
    } catch {
      notif(t('tables.messages.save_failed'), {
        type: 'error'
      })
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

  const statusLabel = s =>
    ({
      available: t('tables.status.available'),
      occupied: t('tables.status.occupied'),
      reserved: t('tables.status.reserved'),
      cleaning: t('tables.status.cleaning'),
      inactive: t('status.inactive')
    })[s] || s

  // Floor plans are only needed for the create/edit form's picker now — the
  // visual floor-plan/map view was removed in favor of a card grid.
  onMounted(() => {
    floorPlanStore.fetchFloorPlans()
    loadTables()
  })

  // Switching the sidebar's active branch must refresh this grid the same
  // way it already does on every other branch-scoped page.
  watch(branchId, loadTables)
</script>

<style scoped>
  .table-card__icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 700;
    border-radius: 8px;
    background: rgb(var(--v-theme-primary), 0.1);
    color: rgb(var(--v-theme-primary));
  }
  .table-card__icon.shape-round {
    border-radius: 50%;
  }
  .table-card--clickable {
    cursor: pointer;
    transition:
      transform 0.15s ease,
      box-shadow 0.15s ease;
  }
  .table-card--clickable:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  }
  .table-card--occupied {
    border-color: rgb(var(--v-theme-error)) !important;
  }
  .table-card--reserved {
    border-color: rgb(var(--v-theme-warning)) !important;
  }
  .cursor-pointer {
    cursor: pointer;
  }
  .table-card {
    position: relative;
  }
  .table-drag-handle {
    position: absolute;
    top: 4px;
    left: 4px;
    z-index: 2;
    cursor: grab;
    opacity: 0.5;
  }
  .table-drag-handle:hover {
    opacity: 1;
  }

  .tables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
  }
  .table-tile {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100px;
    padding: 12px;
    border-radius: 12px;
    cursor: pointer;
    border: 1.5px solid rgba(var(--v-border-color), var(--v-border-opacity));
    background: rgb(var(--v-theme-surface));
    transition: transform 0.15s ease, box-shadow 0.15s ease;
  }
  .table-tile:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  }
  .table-tile--occupied {
    background: rgba(var(--v-theme-warning), 0.08);
    border-color: rgb(var(--v-theme-warning));
  }
  .table-tile__top {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
  }
  .table-tile__number {
    font-weight: 700;
  }
  .table-tile__dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    flex: none;
  }
  .table-tile__dot--occupied {
    background: rgb(var(--v-theme-warning));
  }
  .table-tile__dot--free {
    background: rgb(var(--v-theme-success));
  }
  .table-tile__dot--other {
    background: #9e9e9e;
  }
  .table-tile__middle {
    font-size: 13px;
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  }
  .table-tile--occupied .table-tile__middle {
    font-size: 20px;
    font-weight: 700;
    color: rgb(var(--v-theme-on-surface));
  }
  .table-tile__bottom {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  }
</style>
