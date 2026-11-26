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

    <MobileOrdersCarousel
      v-if="showMobileOrdersTables"
      :branch-id="branchId"
      class="mb-3"
    />

    <div class="d-flex align-center flex-wrap ga-1 mb-2">
      <span class="text-body-medium">
        {{ t('tables.summary_count', { count: tableStats.total }) }}
      </span>
      <span class="text-body-medium text-medium-emphasis">·</span>
      <span class="text-body-medium text-warning font-weight-medium">
        {{ t('tables.summary_occupied', { count: tableStats.occupied }) }}
      </span>
      <template v-if="tableStats.occupied">
        <span class="text-body-medium text-medium-emphasis">·</span>
        <span class="text-body-medium font-weight-medium">
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
        variant="tonal"
        density="compact"
        rounded="lg"
        color="primary"
        icon="mdi-drag"
        class="mb-4"
      >
        {{
          search
            ? $t('tables.reorder.clear_search_hint')
            : $t('tables.reorder.hint')
        }}
      </v-alert>

      <draggable
        v-model="manageTables"
        tag="div"
        :class="['tables-grid', { 'tables-grid--manage': mode === 'manage' }]"
        item-key="id"
        handle=".table-card__handle"
        ghost-class="table-card--ghost"
        :disabled="mode !== 'manage' || !!search || reordering"
        @end="onReorderEnd"
      >
        <template #item="{ element: table }">
          <div>
            <div
              v-if="mode === 'order'"
              class="table-tile"
              :class="tileClass(table)"
              @click="onCardClick(table)"
            >
              <div class="table-tile__top">
                <span class="table-tile__number">{{ table.table_number }}</span>
                <span
                  class="table-tile__dot"
                  :class="`table-tile__dot--${tileState(table)}`"
                />
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
                  {{
                    t('tables.order.pax_orders', {
                      pax: table.capacity,
                      orders: table.open_session?.order_count ?? 0
                    })
                  }}
                </span>
                <span v-else>
                  {{ t('tables.order.seats', { count: table.capacity }) }}
                </span>
              </div>
            </div>

            <v-card
              v-else
              rounded="lg"
              elevation="0"
              border
              class="table-card"
              :class="`table-card--${table.status}`"
            >
              <div class="table-card__head">
                <v-icon
                  v-if="!search"
                  icon="mdi-drag"
                  size="20"
                  class="table-card__handle"
                />
                <span v-else />
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

              <div class="table-card__body">
                <div class="table-card__label text-body-small">
                  {{ $t('tables.table_prefix') }}
                </div>
                <div class="table-card__number">{{ table.table_number }}</div>
                <div class="table-card__seats text-body-small">
                  <v-icon icon="mdi-account-group-outline" size="16" />
                  {{ table.capacity }}
                </div>
              </div>

              <div class="table-card__actions" @click.stop>
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

    <TableFormDialog
      v-model="dialog"
      :item="selectedItem"
      :floor-plans="floorPlanOptions"
      :loading="saving"
      @save="handleSave"
    />

    <AppDialog
      v-model="statusDialog"
      :max-width="360"
      :title="
        $t('tables.status_dialog_title', { number: statusTarget?.table_number })
      "
    >
      <v-row density="compact">
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
  import { useAppUtils, AppDialog, AppToolbar } from '@nong-official-dev/core'
  import MobileOrdersCarousel from '@/components/pos/MobileOrdersCarousel.vue'
  import { useCurrency } from '@/composables/useCurrency_v2.js'

  const { confirm, notif } = useAppUtils()
  const { t } = useI18n()
  const router = useRouter()
  const { formatBase } = useCurrency()

  const authStore = useAuthStore()
  const tableStore = useTableStore()
  const floorPlanStore = useFloorPlanStore()
  const posStore = usePosStore()

  const branchId = computed(
    () => authStore.activeBranchId || authStore.branch_id
  )
  const showMobileOrdersTables = computed(
    () => authStore.posSettings?.show_mobile_menu_orders_tables !== false
  )

  const mode = ref('order')
  const search = ref('')
  const loading = ref(false)
  const statusFilter = ref('all')

  const saving = ref(false)
  const dialog = ref(false)
  const statusDialog = ref(false)
  const selectedItem = ref(null)
  const statusTarget = ref(null)
  const newStatus = ref(null)

  const qrDialog = ref(false)
  const qrTarget = ref(null)

  const orderDialog = ref(false)
  const orderDialogOrders = ref([])
  const orderDialogTable = ref(null)
  const paymentDialog = ref(false)

  const floorPlanOptions = computed(() => floorPlanStore.floorPlans)

  // Always a new array so watchers fire when a single table's status changes.
  const filteredTables = computed(() => {
    let list = [...tableStore.tables]
    if (statusFilter.value === 'occupied')
      list = list.filter(table => table.is_occupied)
    else if (statusFilter.value === 'free')
      list = list.filter(table => !table.is_occupied)

    const q = search.value?.trim().toLowerCase()
    if (!q) return list
    return list.filter(table =>
      String(table.table_number).toLowerCase().includes(q)
    )
  })

  const tableStats = computed(() => {
    const occupiedTables = tableStore.tables.filter(table => table.is_occupied)
    return {
      total: tableStore.tables.length,
      occupied: occupiedTables.length,
      free: tableStore.tables.length - occupiedTables.length,
      dueTotal: occupiedTables.reduce(
        (sum, table) => sum + Number(table.open_session?.total ?? 0),
        0
      )
    }
  })

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

  const tileClass = table => `table-tile--${tileState(table)}`

  const manageTables = ref([])
  watch(
    filteredTables,
    val => {
      manageTables.value = [...val]
    },
    { immediate: true }
  )

  const reordering = ref(false)

  async function onReorderEnd() {
    if (mode.value !== 'manage' || search.value) return
    reordering.value = true
    try {
      await tableStore.reorderTables(manageTables.value.map(tb => tb.id))
      notif(t('tables.reorder.saved'), { type: 'success' })
    } catch {
      notif(t('tables.reorder.failed'), { type: 'error' })
      await loadTables()
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

  function onPaid() {
    paymentDialog.value = false
    orderDialog.value = false
    loadTables()
  }

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

  const openCreate = () => {
    selectedItem.value = null
    dialog.value = true
  }

  const openEdit = table => {
    selectedItem.value = { ...table }
    dialog.value = true
  }

  const openQR = table => {
    qrTarget.value = table
    qrDialog.value = true
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

  const openStatusChange = table => {
    statusTarget.value = table
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
      notif(t('tables.messages.status_update_failed'), { type: 'error' })
    } finally {
      saving.value = false
      newStatus.value = null
    }
  }

  const confirmDelete = data => {
    confirm({
      title: t('tables.confirm_delete.title'),
      message: t('tables.confirm_delete.message', {
        number: data.table_number
      }),
      options: { type: 'warning', width: 550 },
      agree: async () => {
        try {
          await tableStore.deleteTable(data.id)
          notif(t('tables.messages.deleted', { number: data.table_number }), {
            type: 'success'
          })
        } catch {
          notif(t('tables.messages.delete_failed'), { type: 'error' })
        }
      },
      cancel: () => {}
    })
  }

  const handleSave = async payload => {
    saving.value = true
    try {
      if (payload.id) {
        await tableStore.updateTable(payload.id, payload)
        notif(t('tables.messages.updated'), { type: 'success' })
      } else {
        await tableStore.createTable(payload)
        notif(t('tables.messages.created'), { type: 'success' })
      }
      dialog.value = false
    } catch {
      notif(t('tables.messages.save_failed'), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

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

  onMounted(() => {
    floorPlanStore.fetchFloorPlans()
    loadTables()
  })

  watch(branchId, loadTables)
</script>

<style scoped>
  .cursor-pointer {
    cursor: pointer;
  }

  .tables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
  }
  .tables-grid--manage {
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 14px;
  }

  /* Order mode */
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
    transition:
      transform 0.15s ease,
      box-shadow 0.15s ease;
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

  /* Manage mode */
  .table-card {
    --accent: 158, 158, 158;
    position: relative;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition:
      transform 0.15s ease,
      box-shadow 0.15s ease;
  }
  .table-card::before {
    content: '';
    position: absolute;
    inset: 0 0 auto 0;
    height: 3px;
    background: rgb(var(--accent));
  }
  .table-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
  }
  .table-card--available {
    --accent: var(--v-theme-success);
  }
  .table-card--occupied {
    --accent: var(--v-theme-error);
  }
  .table-card--reserved {
    --accent: var(--v-theme-warning);
  }
  .table-card--cleaning {
    --accent: var(--v-theme-info);
  }
  .table-card--ghost {
    opacity: 0.4;
  }
  .table-card--inactive {
    opacity: 0.7;
  }

  .table-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 10px 0 8px;
  }
  .table-card__handle {
    cursor: grab;
    opacity: 0.4;
  }
  .table-card__handle:hover {
    opacity: 1;
  }
  .table-card__handle:active {
    cursor: grabbing;
  }

  .table-card__body {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 8px 12px 14px;
  }
  .table-card__label,
  .table-card__seats {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  }
  .table-card__number {
    font-size: 32px;
    font-weight: 700;
    line-height: 1.1;
    color: rgb(var(--accent));
  }
  .table-card__seats {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 6px;
  }

  .table-card__actions {
    display: flex;
    justify-content: space-around;
    padding: 4px;
    margin-top: auto;
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }
</style>
