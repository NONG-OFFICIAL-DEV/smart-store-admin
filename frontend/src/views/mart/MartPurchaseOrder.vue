<template>
  <div>
    <AppToolbar :title="t('purchase_order.title')" :subtitle="t('purchase_order.subtitle')">
      <template #actions>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          @click="openCreate"
        >
          {{ t('btn.create_po') }}
        </v-btn>
      </template>
    </AppToolbar>

    <!-- Stats row -->
    <v-row dense class="mb-4">
      <v-col v-for="s in stats" :key="s.label" cols="6" sm="3">
        <v-card rounded="lg" border elevation="0" class="pa-4">
          <div class="text-caption text-medium-emphasis mb-1">
            {{ s.label }}
          </div>
          <div class="text-h6 font-weight-black" :class="`text-${s.color}`">
            {{ s.value }}
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Filters -->
    <v-card rounded="lg" border elevation="0" class="mb-4">
      <v-card-text class="pa-4">
        <v-row dense align="center">
          <v-col cols="12" sm="4">
            <v-text-field
              v-model="filters.search"
              :placeholder="t('purchase_order.search_placeholder')"
              variant="outlined"
              rounded="lg"
              hide-details
              clearable
              prepend-inner-icon="mdi-magnify"
              @update:model-value="onSearch"
            />
          </v-col>
          <v-col cols="6" sm="2">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              item-title="label"
              item-value="value"
              :placeholder="t('form.status')"
              variant="outlined"
              rounded="lg"
              hide-details
              clearable
            />
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Table -->
    <v-card rounded="lg" border elevation="0">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchTableData"
        :filters="appliedFilters"
        :show-search="false"
        item-label="purchase orders"
      >
        <!-- PO Number -->
        <template #item.po_number="{ item }">
          <div class="font-weight-bold text-body-2">{{ item.po_number }}</div>
          <div class="text-caption text-medium-emphasis">
            {{ fmtDate(item.created_at) }}
          </div>
        </template>

        <!-- Supplier -->
        <template #item.supplier="{ item }">
          <div class="text-body-2">{{ item.supplier?.name }}</div>
        </template>

        <!-- Items count -->
        <template #item.items_count="{ item }">
          <v-chip size="small" variant="tonal" rounded="lg">
            {{ t('po.items_count', item.items_count ?? item.items?.length ?? 0) }}
          </v-chip>
        </template>

        <!-- Total -->
        <template #item.total_amount="{ item }">
          <span class="font-weight-bold">{{ format(item.total_amount) }}</span>
        </template>

        <!-- Expected delivery -->
        <template #item.expected_delivery="{ item }">
          <span v-if="item.expected_delivery" class="text-body-2">
            {{ fmtDate(item.expected_delivery) }}
          </span>
          <span v-else class="text-caption text-medium-emphasis">—</span>
        </template>

        <!-- Status -->
        <template #item.status="{ item }">
          <v-chip
            size="small"
            rounded="lg"
            variant="tonal"
            :color="statusColor(item.status)"
          >
            {{ item.status.replace('_', ' ') }}
          </v-chip>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <v-btn
              icon="mdi-eye-outline"
              size="small"
              variant="text"
              @click="openDetail(item)"
            />
            <v-btn
              v-if="canEdit(item)"
              icon="mdi-pencil-outline"
              size="small"
              variant="text"
              @click="openEdit(item)"
            />
            <!-- v-if="canReceive(item)" -->
            <v-btn
              icon="mdi-package-down"
              size="small"
              variant="text"
              color="success"
              :title="t('purchase_order.receive_stock')"
              @click="openReceive(item)"
            />
            <v-btn
              v-if="canCancel(item)"
              icon="mdi-close-circle-outline"
              size="small"
              variant="text"
              color="warning"
              @click="confirmCancel(item)"
            />
            <v-btn
              v-if="item.status === 'draft'"
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

    <!-- Receive Dialog -->
    <MartPoReceiveDialog
      v-model="receiveDialog"
      :po="selectedPo"
      :loading="receiving"
      @receive="handleReceive"
    />

    <!-- Detail Dialog -->
    <MartPoDetailDialog v-model="detailDialog" :po="detailPo" />

    <!-- Cancel confirm -->
    <AppDialog
      v-model="cancelDialog"
      :max-width="400"
      :persistent="false"
      :title="t('po.confirm_cancel.title')"
      :loading="cancelling"
    >
      <template #actions="{ loading }">
        <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="cancelDialog = false">
          {{ t('btn.back') }}
        </v-btn>
        <v-btn color="warning" variant="flat" rounded="lg" :loading="loading" @click="doCancel">
          {{ t('po.confirm_cancel.confirm') }}
        </v-btn>
      </template>
    </AppDialog>
  </div>
</template>

<script setup>
  import { ref, computed } from 'vue'
  import { useMartPurchaseOrderStore } from '@/stores/martPurchaseOrderStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import { AppTable, AppDialog } from '@nong-official-dev/core'
  import AppToolbar from '@/components/common/AppToolbar.vue'
  import MartPoReceiveDialog from '@/components/mart/MartPoReceiveDialog.vue'
  import MartPoDetailDialog from '@/components/mart/MartPoDetailDialog.vue'
  import { useRouter } from 'vue-router'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import { useI18n } from 'vue-i18n'
  import { useDate } from '@/composables/useDate'

  const poStore = useMartPurchaseOrderStore()
  const { notif, confirm } = useAppUtils()
  const { format } = useCurrency()
  const { t } = useI18n()
  const { formatShortDate: fmtDate } = useDate()

  const router = useRouter()
  const receiveDialog = ref(false)
  const detailDialog = ref(false)
  const cancelDialog = ref(false)
  const selectedPo = ref(null)
  const detailPo = ref(null)
  const cancelTarget = ref(null)
  const receiving = ref(false)
  const cancelling = ref(false)

  const tableRef = ref(null)

  const filters = ref({
    branch_id: null,
    search: '',
    status: null
  })
  // Only synced from filters.search after the debounce below — status
  // applies instantly since it's a discrete choice, not continuous typing.
  const appliedSearch = ref('')

  const statusOptions = computed(() => [
    { value: 'draft', label: t('po.status.draft') },
    { value: 'submitted', label: t('po.status.submitted') },
    { value: 'confirmed', label: t('po.status.confirmed') },
    { value: 'partially_received', label: t('po.status.partially_received') },
    { value: 'received', label: t('po.status.received') },
    { value: 'cancelled', label: t('po.status.cancelled') }
  ])

  const headers = computed(() => [
    { title: t('po.table.po_number'), key: 'po_number', sortable: false },
    { title: t('po.table.supplier'), key: 'supplier', sortable: false },
    { title: t('po.table.items'), key: 'items_count', sortable: false },
    { title: t('po.table.total'), key: 'total_amount', sortable: false },
    { title: t('po.table.expected'), key: 'expected_delivery', sortable: false },
    { title: t('form.status'), key: 'status', sortable: false },
    { title: '', key: 'actions', sortable: false, width: '160' }
  ])

  // Replace these two methods:
  const openCreate = () => router.push({ name: 'MartPurchaseOrderCreate' })
  const openEdit = po =>
    router.push({ name: 'MartPurchaseOrderEdit', params: { id: po.id } })

  // Then remove: dialog ref, MartPoDialog import, handleSave, saving
  const stats = computed(() => {
    const orders = poStore.orders
    return [
      {
        label: t('purchase_order.summary.total'),
        value: poStore.pagination?.total ?? 0,
        color: 'primary'
      },
      {
        label: t('purchase_order.summary.draft'),
        value: orders.filter(o => o.status === 'draft').length,
        color: 'grey'
      },
      {
        label: t('purchase_order.summary.in_progress'),
        value: orders.filter(o =>
          ['submitted', 'confirmed', 'partially_received'].includes(o.status)
        ).length,
        color: 'warning'
      },
      {
        label: t('purchase_order.summary.received'),
        value: orders.filter(o => o.status === 'received').length,
        color: 'success'
      }
    ]
  })

  const statusColor = s =>
    ({
      draft: 'grey',
      submitted: 'blue',
      confirmed: 'indigo',
      partially_received: 'warning',
      received: 'success',
      cancelled: 'error'
    })[s] ?? 'grey'

  const canEdit = o => ['draft', 'submitted'].includes(o.status)
  const canCancel = o => !['received', 'cancelled'].includes(o.status)


  // ── Filters passed straight through to fetchTableData — AppTable deep-watches
  // this and refetches (resetting to page 1) whenever it changes. ───────────────
  const appliedFilters = computed(() => ({
    search: appliedSearch.value || undefined,
    status: filters.value.status || undefined,
    branch_id: filters.value.branch_id || undefined
  }))

  async function fetchTableData(params) {
    await poStore.fetchOrders(params)
    return { items: poStore.orders ?? [], total: poStore.pagination?.total ?? 0 }
  }

  let searchTimer = null
  const onSearch = () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
      appliedSearch.value = filters.value.search
    }, 400)
  }

  const openReceive = po => {
    selectedPo.value = po
    receiveDialog.value = true
  }
  const openDetail = async po => {
    detailPo.value = await poStore.fetchOrder(po.id)
    detailDialog.value = true
  }

  const handleReceive = async payload => {
    receiving.value = true
    try {
      const res = await poStore.receiveOrder(selectedPo.value.id, payload)
      notif(res.message ?? t('po.messages.received'), { type: 'success' })
      receiveDialog.value = false
      tableRef.value?.refresh()
    } catch (e) {
      notif(e.response?.data?.message ?? t('po.messages.receive_failed'), { type: 'error' })
    } finally {
      receiving.value = false
    }
  }

  const confirmCancel = po => {
    cancelTarget.value = po
    cancelDialog.value = true
  }
  const doCancel = async () => {
    cancelling.value = true
    try {
      await poStore.cancelOrder(cancelTarget.value.id)
      notif(t('po.messages.cancelled'), { type: 'success' })
      cancelDialog.value = false
      tableRef.value?.refresh()
    } catch (e) {
      notif(e.response?.data?.message ?? t('po.messages.cancel_failed'), { type: 'error' })
    } finally {
      cancelling.value = false
    }
  }

  const confirmDelete = async po => {
    try {
      confirm({
        title: t('purchase_order.delete_title'),
        message: `${po.po_number}`,
        options: { type: 'warning', width: 500 },
        agree: async () => {
          await poStore.deleteOrder(po.id)
          notif(t('purchase_order.deleted'), { type: 'success' })
          tableRef.value?.refresh()
        },
        cancel: () => {}
      })
    } catch (e) {
      notif(e.response?.data?.message ?? t('branch_menu.operation_failed'), { type: 'error' })
    }
  }
</script>

<style scoped>
  .gap-1 {
    gap: 4px;
  }
  .gap-2 {
    gap: 8px;
  }
  .gap-3 {
    gap: 12px;
  }
</style>
