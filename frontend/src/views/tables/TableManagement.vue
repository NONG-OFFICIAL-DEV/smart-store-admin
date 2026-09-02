<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-table"
      :title="$t('menu.tables')"
      :subtitle="$t('tables.subtitle')"
    >
      <template #right>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          rounded="lg"
          elevation="0"
          @click="openCreate"
        >
          {{ $t('tables.add') }}
        </v-btn>
      </template>
    </custom-title>

    <v-card rounded="lg" elevation="0" border class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchTablesForTable"
        :show-search="true"
        :item-label="$t('menu.tables')"
      >
        <!-- Table number -->
        <template #item.table_number="{ item }">
          <div class="d-flex align-center gap-2">
            <div
              class="table-icon mini"
              :class="`shape-${item.shape || 'square'}`"
            >
              {{ item.table_number }}
            </div>
            <div>
              <div class="text-body-2 font-weight-medium">
                {{ $t('tables.table_prefix') }} {{ item.table_number }}
              </div>
              <div class="text-caption text-grey">{{ shapeLabel(item.shape) }}</div>
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
            {{ statusLabel(item.status) }}
          </v-chip>
        </template>

        <!-- Active -->
        <template #item.is_active="{ item }">
          <v-chip
            :color="item.is_active ? 'success' : 'grey'"
            size="x-small"
            variant="tonal"
          >
            {{ item.is_active ? $t('status.active') : $t('status.inactive') }}
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
      </AppTable>
    </v-card>

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
      :title="$t('tables.status_dialog_title', { number: statusTarget?.table_number })"
      icon="mdi-swap-horizontal"
      color="primary"
      body-class="pa-4"
      :hide-actions="true"
    >
      <div v-if="activeOrderLoading" class="d-flex justify-center py-4">
        <v-progress-circular indeterminate color="primary" size="24" />
      </div>

      <v-card
        v-else-if="activeOrder"
        rounded="lg"
        variant="tonal"
        color="primary"
        class="pa-3 mb-4"
      >
        <div class="d-flex justify-space-between align-center mb-1">
          <span class="text-body-2 font-weight-bold">{{ activeOrder.order_number }}</span>
          <span class="text-body-2 font-weight-black">{{ formatMoney(activeOrder.total_amount) }}</span>
        </div>
        <div class="text-caption text-medium-emphasis">
          {{ $t('tables.current_order.items_count', { n: activeOrder.items?.length ?? 0 }) }}
        </div>
      </v-card>

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
  </v-container>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useRouter } from 'vue-router'
  import { useI18n } from 'vue-i18n'
  import { useTableStore } from '@/stores/tableStore'
  import { useFloorPlanStore } from '@/stores/floorPlanStore'
  import TableFormDialog from '@/components/tables/TableFormDialog.vue'
  import TableQRDialog from '@/components/tables/TableQRDialog.vue'
  import AppDialog from '@/components/common/AppDialog.vue'
  import { useAppUtils, AppTable } from '@nong-official-dev/core'
  const { confirm, notif } = useAppUtils()
  const { t } = useI18n()

  const qrDialog = ref(false)
  const qrTarget = ref(null)
  const openQR = table => {
    qrTarget.value = table
    qrDialog.value = true
  }

  const router = useRouter()
  const tableStore = useTableStore()
  const floorPlanStore = useFloorPlanStore()

  const tableRef = ref(null)
  const activeOrder = ref(null)
  const activeOrderLoading = ref(false)

  const saving = ref(false)
  const dialog = ref(false)
  const statusDialog = ref(false)
  const selectedItem = ref(null)
  const statusTarget = ref(null)
  const newStatus = ref(null)

  // Real floor plans only — used by the create/edit form's picker.
  const floorPlanOptions = computed(() => floorPlanStore.floorPlans)

  async function fetchTablesForTable(params) {
    await tableStore.fetchTables(params)
    return { items: tableStore.tables, total: tableStore.pagination?.total ?? 0 }
  }

  // Table headers for AppTable
  const headers = [
    { title: t('tables.table_headers.table'), key: 'table_number', sortable: true },
    { title: t('tables.table_headers.capacity'), key: 'capacity', sortable: true },
    { title: t('form.status'), key: 'status', sortable: true },
    { title: t('tables.table_headers.active'), key: 'is_active', sortable: false },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ]

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
    { value: 'cleaning', label: t('tables.status.cleaning'), color: 'blue', icon: 'mdi-broom' },
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

  const openStatusChange = async t => {
    statusTarget.value = t
    statusDialog.value = true
    activeOrder.value = null
    if (t.status === 'occupied') {
      activeOrderLoading.value = true
      try {
        activeOrder.value = await tableStore.fetchActiveOrderForTable(t.id)
      } finally {
        activeOrderLoading.value = false
      }
    }
  }
  const openReservation = t =>
    router.push({ name: 'Reservations', query: { table_id: t.id } })

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
      tableRef.value?.refresh()
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
          tableRef.value?.refresh()
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
      tableRef.value?.refresh()
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

  const formatMoney = value =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value ?? 0)

  const shapeLabel = shape =>
    ({
      round: t('tables.shapes.round'),
      square: t('tables.shapes.square'),
      rectangle: t('tables.shapes.rectangle'),
      bar: t('tables.shapes.bar')
    })[shape] || shape

  // Floor plans are only needed for the create/edit form's picker now — the
  // visual floor-plan/map view was removed in favor of a single AppTable list.
  onMounted(() => {
    floorPlanStore.fetchFloorPlans()
  })
</script>

<style scoped>
  /* Mini table-number icon */
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
  .gap-1 {
    gap: 4px;
  }
  .gap-2 {
    gap: 8px;
  }
  .cursor-pointer {
    cursor: pointer;
  }
</style>
