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

    <div class="d-flex align-center flex-wrap ga-3 mb-4">
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

    <v-row v-else dense>
      <v-col
        v-for="table in filteredTables"
        :key="table.id"
        cols="6"
        sm="4"
        md="3"
        lg="2"
      >
        <v-card
          rounded="lg"
          elevation="0"
          border
          class="table-card"
          :class="[
            `table-card--${table.status}`,
            { 'table-card--clickable': mode === 'order' }
          ]"
          @click="onCardClick(table)"
        >
          <div class="pa-3 d-flex flex-column align-center text-center">
            <div
              class="table-card__icon"
              :class="`shape-${table.shape || 'square'}`"
            >
              {{ table.table_number }}
            </div>
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

          <template v-if="mode === 'manage'">
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
          </template>
          <template v-else>
            <v-divider />
            <div class="pa-2 text-center text-caption text-medium-emphasis">
              {{ orderModeHint(table) }}
            </div>
          </template>
        </v-card>
      </v-col>
    </v-row>

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

    <!-- Always the same dialog whether the table has one order or several
         — OrderDetailDialog combines every open order's items into one
         merged list, so there's nothing to pick between first. Paying or
         printing the bill is how staff closes out a table here, so either
         one frees the table once it happens. -->
    <OrderDetailDialog
      v-model="orderDialog"
      :orders="orderDialogOrders"
      free-table-on-complete
    />
  </v-container>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRouter } from 'vue-router'
  import { useTableStore } from '@/stores/tableStore'
  import { useFloorPlanStore } from '@/stores/floorPlanStore'
  import { usePosStore } from '@/stores/posStore'
  import TableFormDialog from '@/components/tables/TableFormDialog.vue'
  import TableQRDialog from '@/components/tables/TableQRDialog.vue'
  import OrderDetailDialog from '@/components/orders/OrderDetailDialog.vue'
  import { useAppUtils, AppDialog } from '@nong-official-dev/core'
  import AppToolbar from '@/components/common/AppToolbar.vue'
  const { confirm, notif } = useAppUtils()
  const { t } = useI18n()
  const router = useRouter()

  const qrDialog = ref(false)
  const qrTarget = ref(null)
  const openQR = table => {
    qrTarget.value = table
    qrDialog.value = true
  }

  const tableStore = useTableStore()
  const floorPlanStore = useFloorPlanStore()
  const posStore = usePosStore()

  // Order mode — tap a table to either view its active order (occupied) or
  // jump into Food POS with the table preselected (available). Manage mode
  // is the original create/edit/delete/QR workflow, unchanged.
  const mode = ref('order')
  const search = ref('')
  const loading = ref(false)

  const saving = ref(false)
  const dialog = ref(false)
  const statusDialog = ref(false)
  const selectedItem = ref(null)
  const statusTarget = ref(null)
  const newStatus = ref(null)

  const orderDialog = ref(false)
  const orderDialogOrders = ref([])

  // Real floor plans only — used by the create/edit form's picker.
  const floorPlanOptions = computed(() => floorPlanStore.floorPlans)

  const filteredTables = computed(() => {
    const q = search.value?.trim().toLowerCase()
    if (!q) return tableStore.tables
    return tableStore.tables.filter(table =>
      String(table.table_number).toLowerCase().includes(q)
    )
  })

  async function loadTables() {
    loading.value = true
    try {
      await tableStore.fetchTables({ perPage: -1 })
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
    if (table.status === 'occupied') return viewTableOrder(table)
    if (table.status === 'available') return startOrderAtTable(table)
    openStatusChange(table)
  }

  function orderModeHint(table) {
    if (table.status === 'occupied') return t('tables.order.view_order')
    if (table.status === 'available') return t('tables.order.start_order')
    return t('tables.change_status')
  }

  async function viewTableOrder(table) {
    try {
      const orders = await tableStore.fetchOrdersForTable(table.id)
      if (!orders.length) {
        notif(t('tables.order.no_active_order'), { type: 'warning' })
        return
      }
      orderDialogOrders.value = orders
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
</style>
