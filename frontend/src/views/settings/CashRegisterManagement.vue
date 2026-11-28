<template>
  <v-container fluid class="pa-0">
    <AppToolbar :title="t('cash_registers.title')" :subtitle="t('cash_registers.subtitle')">
      <template #actions>
        <v-btn
          v-if="tab === 'registers'"
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          @click="openAdd"
        >
          {{ t('cash_registers.add') }}
        </v-btn>
      </template>
    </AppToolbar>

    <v-tabs v-model="tab" class="mb-4">
      <v-tab value="registers">{{ t('cash_registers.tabs.registers') }}</v-tab>
      <v-tab value="history">{{ t('cash_registers.view_history') }}</v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <v-window-item value="registers">
        <v-card rounded="lg" elevation="0" border class="pa-4">
          <AppTable
            ref="tableRef"
            :headers="headers"
            :fetch-fn="fetchTableData"
            item-label="cash_registers"
          >
            <template #item.branch_id="{ item }">
              {{ branchName(item.branch_id) }}
            </template>

            <template #item.is_active="{ item }">
              <AppStatusChip :status="item.is_active ? 'active' : 'inactive'" size="x-small" />
            </template>

            <template #item.has_open_shift="{ item }">
              <AppStatusChip
                :status="item.has_open_shift ? 'open' : 'closed'"
                :map="shiftStatusMap"
                size="x-small"
              />
            </template>

            <template #item.actions="{ item }">
              <v-btn icon="mdi-pencil-outline" size="small" variant="text" color="primary" class="mr-1" @click="openEdit(item)" />
              <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" @click="handleDelete(item.id)" />
            </template>
          </AppTable>
        </v-card>
      </v-window-item>

      <!-- Shift History — full page width, not squeezed into a dialog;
           this table has 9 columns and needs the room. -->
      <v-window-item value="history">
        <v-card rounded="lg" elevation="0" border class="pa-4">
          <v-data-table
            :headers="historyHeaders"
            :items="drawerStore.cashDrawers"
            :items-per-page="15"
            density="comfortable"
          >
            <template #item.staff_name="{ item }">{{ item.staff_name ?? '—' }}</template>
            <template #item.cash_register_name="{ item }">{{ item.cash_register_name ?? branchName(item.branch_id) }}</template>
            <template #item.opened_at="{ item }">{{ formatDateTime(item.opened_at) }}</template>
            <template #item.closed_at="{ item }">{{ item.closed_at ? formatDateTime(item.closed_at) : '—' }}</template>
            <template #item.opening_float="{ item }">{{ formatMoney(item.opening_float) }}</template>
            <template #item.expected_cash="{ item }">{{ item.expected_cash != null ? formatMoney(item.expected_cash) : '—' }}</template>
            <template #item.actual_cash="{ item }">{{ item.actual_cash != null ? formatMoney(item.actual_cash) : '—' }}</template>
            <template #item.variance="{ item }">
              <span v-if="item.variance == null">—</span>
              <span v-else :class="varianceClass(item.variance)">{{ formatMoney(item.variance) }}</span>
            </template>
            <template #item.status="{ item }">
              <AppStatusChip :status="item.closed_at ? 'closed' : 'open'" :map="shiftStatusMap" size="x-small" />
            </template>
          </v-data-table>
        </v-card>
      </v-window-item>
    </v-window>

    <CashRegisterDialog
      v-model="dialog"
      :cash-register="selected"
      :branches="branchStore.branches"
      :loading="saving"
      @save="handleSave"
    />
  </v-container>
</template>

<script setup>
  import { ref, computed, onMounted, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { AppStatusChip, AppTable, AppToolbar, useAppUtils } from '@nong-official-dev/core'
  import { useCashRegisterStore } from '@/stores/cashRegisterStore'
  import { useCashDrawerStore } from '@/stores/cashDrawerStore'
  import { useBranchStore } from '@/stores/branchStore'
  import CashRegisterDialog from '@/components/CashRegisterDialog.vue'

  const { t } = useI18n()
  const store = useCashRegisterStore()
  const drawerStore = useCashDrawerStore()
  const branchStore = useBranchStore()
  const { confirm, notif } = useAppUtils()

  onMounted(() => {
    if (!branchStore.branches.length) branchStore.fetchBranches({ perPage: -1 })
  })

  const branchName = id => branchStore.branches.find(b => b.id === id)?.name ?? '—'

  const shiftStatusMap = {
    open: { color: 'success', label: t('cash_registers.status.open') },
    closed: { color: 'grey', label: t('cash_registers.status.closed') }
  }

  // ── Table ──────────────────────────────────────────────────────────────
  const tableRef = ref(null)

  const headers = computed(() => [
    { title: t('cash_registers.table.name'), key: 'name' },
    { title: t('cash_registers.table.branch'), key: 'branch_id' },
    { title: t('cash_registers.table.status'), key: 'is_active' },
    { title: t('cash_registers.table.shift'), key: 'has_open_shift' },
    { title: t('suppliers.table.actions'), key: 'actions', sortable: false, align: 'end' }
  ])

  async function fetchTableData(params) {
    await store.fetchCashRegisters(params)
    return { items: store.cashRegisters.data ?? [], total: store.cashRegisters.total ?? 0 }
  }

  // ── Dialog ─────────────────────────────────────────────────────────────
  const dialog = ref(false)
  const selected = ref(null)
  const saving = ref(false)

  const openAdd = () => {
    selected.value = null
    dialog.value = true
  }
  const openEdit = item => {
    selected.value = { ...item }
    dialog.value = true
  }

  const handleSave = async data => {
    saving.value = true
    try {
      if (data.id) {
        await store.updateCashRegister(data)
        notif(t('cash_registers.updated'), { type: 'success' })
      } else {
        await store.addCashRegister(data)
        notif(t('cash_registers.added'), { type: 'success' })
      }
      dialog.value = false
      tableRef.value?.refresh()
    } catch (e) {
      notif(e?.response?.data?.message ?? t('errors.unknown'), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  const handleDelete = id => {
    confirm({
      title: t('cash_registers.delete_title'),
      message: t('cash_registers.delete_message'),
      options: { type: 'error' },
      agree: async () => {
        await store.removeCashRegister(id)
        notif(t('cash_registers.deleted'), { type: 'success' })
        tableRef.value?.refresh()
      }
    })
  }

  // ── Tabs / History ───────────────────────────────────────────────────
  const tab = ref('registers')

  const historyHeaders = computed(() => [
    { title: t('cash_registers.history.cashier'), key: 'staff_name' },
    { title: t('cash_registers.history.register'), key: 'cash_register_name' },
    { title: t('cash_registers.history.opened_at'), key: 'opened_at' },
    { title: t('cash_registers.history.closed_at'), key: 'closed_at' },
    { title: t('cash_registers.history.opening_float'), key: 'opening_float' },
    { title: t('cash_registers.history.expected_cash'), key: 'expected_cash' },
    { title: t('cash_registers.history.actual_cash'), key: 'actual_cash' },
    { title: t('cash_registers.history.variance'), key: 'variance' },
    { title: t('cash_registers.history.status'), key: 'status' }
  ])

  watch(tab, value => {
    if (value === 'history') drawerStore.fetchCashDrawers({ perPage: -1, sortDesc: true })
  })

  function formatMoney(value) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value ?? 0)
  }
  function formatDateTime(value) {
    return value ? new Date(value).toLocaleString() : '—'
  }
  function varianceClass(value) {
    if (value > 0) return 'text-info font-weight-bold'
    if (value < 0) return 'text-error font-weight-bold'
    return 'text-success font-weight-bold'
  }
</script>
