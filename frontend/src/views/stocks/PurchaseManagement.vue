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
          {{ $t('btn.create_po') }}
        </v-btn>
      </template>
    </AppToolbar>

    <!-- ── Filters — always visible, no toggle needed for just two selects ──── -->
    <v-card rounded="lg" border elevation="0" class="mb-4">
      <v-card-text class="pa-4">
        <v-row dense align="center">
          <v-col cols="6" sm="3">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              item-title="label"
              item-value="value"
              :placeholder="$t('form.status')"
              variant="outlined"
              rounded="lg"
              hide-details
              clearable
            />
          </v-col>
          <v-col cols="6" sm="3">
            <v-select
              v-model="filters.supplier_id"
              :items="supplierStore.suppliers.data"
              item-title="name"
              item-value="id"
              :placeholder="$t('form.supplier')"
              variant="outlined"
              rounded="lg"
              hide-details
              clearable
            />
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>
    <!-- ── Table ──────────────────────────────────────────────────────────── -->
    <v-card rounded="lg" border elevation="0" class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchTableData"
        :filters="appliedFilters"
        item-label="purchase orders"
      >
        <!-- PO Number -->
        <template #item.po_number="{ item }">
          <div class="font-weight-bold text-body-2">{{ item.po_number }}</div>
          <div class="text-caption text-medium-emphasis">
            {{ item.branch?.name }}
          </div>
        </template>

        <!-- Supplier -->
        <template #item.supplier="{ item }">
          <div class="text-body-2">{{ item.supplier?.name }}</div>
        </template>

        <!-- Items count -->
        <template #item.items="{ item }">
          <v-chip size="x-small" variant="tonal" rounded="lg">
            {{ $t('po.items_count', item.items?.length ?? 0) }}
          </v-chip>
        </template>

        <!-- Total -->
        <template #item.total_amount="{ item }">
          <span class="font-weight-bold">{{ fmt(item.total_amount) }}</span>
        </template>

        <!-- Expected -->
        <template #item.expected_delivery="{ item }">
          <span class="text-body-2">{{ fmtDate(item.expected_delivery ?? '—') }}</span>
        </template>

        <!-- Status -->
        <template #item.status="{ item }">
          <v-chip
            size="small"
            rounded="lg"
            variant="tonal"
            :color="statusColor(item.status)"
          >
            <v-icon start size="12" :icon="statusIcon(item.status)" />
            {{ statusLabel(item.status) }}
          </v-chip>
        </template>

        <!-- Date -->
        <template #item.created_at="{ item }">
          <div class="text-body-2">{{ fmtDate(item.created_at) }}</div>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <v-btn
              icon="mdi-eye-outline"
              size="small"
              variant="text"
              color="primary"
              @click="openDetail(item)"
            />
            <v-btn
              v-if="canSubmit(item)"
              icon="mdi-send-outline"
              size="small"
              variant="text"
              color="info"
              :title="$t('po.submit_po')"
              @click="doSubmit(item)"
            />
            <v-btn
              v-if="canConfirm(item)"
              icon="mdi-check"
              size="small"
              variant="text"
              color="primary"
              :title="$t('po.confirm_po')"
              @click="doConfirm(item)"
            />
            <v-btn
              v-if="canReceive(item)"
              icon="mdi-package-down"
              size="small"
              variant="text"
              color="success"
              :title="$t('po.receive')"
              @click="openReceive(item)"
            />
            <v-btn
              v-if="canEdit(item)"
              icon="mdi-pencil-outline"
              size="small"
              variant="text"
              @click="openEdit(item)"
            />
            <v-btn
              v-if="canCancel(item)"
              icon="mdi-cancel"
              size="small"
              variant="text"
              color="error"
              @click="confirmCancel(item)"
            />
          </div>
        </template>
      </AppTable>
    </v-card>

    <!-- ── Create/Edit Dialog ─────────────────────────────────────────────── -->
    <PurchaseOrderDialog
      v-model="dialog"
      :purchase-order="selectedPO"
      :loading="saving"
      @save="handleSave"
    />

    <!-- ── Detail Dialog ──────────────────────────────────────────────────── -->
    <PurchaseOrderDetailDialog
      v-model="detailDialog"
      :purchase-order-id="selectedPO?.id"
      @receive="openReceiveFromDetail"
    />

    <!-- ── Receive Dialog ─────────────────────────────────────────────────── -->
    <PurchaseOrderReceiveDialog
      v-model="receiveDialog"
      :purchase-order="receivingPO"
      :loading="receiving"
      @save="handleReceive"
    />

    <!-- ── Cancel Confirm ─────────────────────────────────────────────────── -->
    <AppDialog
      v-model="cancelDialog"
      :max-width="400"
      :title="$t('po.confirm_cancel.title')"
      :loading="cancelling"
    >
      {{ $t('po.confirm_cancel.message') }}
      <template #actions="{ loading }">
        <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="cancelDialog = false">
          {{ $t('po.confirm_cancel.keep') }}
        </v-btn>
        <v-btn color="error" variant="flat" rounded="lg" :loading="loading" @click="doCancel">
          {{ $t('po.confirm_cancel.confirm') }}
        </v-btn>
      </template>
    </AppDialog>
  </div>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { usePurchaseOrderStore } from '@/stores/purchaseOrderStore'
  import { useSupplierStore } from '@/stores/supplierStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import { AppTable, AppDialog } from '@nong-official-dev/core'
  import AppToolbar from '@/components/common/AppToolbar.vue'
  import PurchaseOrderDialog from '@/components/purchase-orders/PurchaseOrderDialog.vue'
  import PurchaseOrderDetailDialog from '@/components/purchase-orders/PurchaseOrderDetailDialog.vue'
  import PurchaseOrderReceiveDialog from '@/components/purchase-orders/PurchaseOrderReceiveDialog.vue'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import { useI18n } from 'vue-i18n'
  import { useDate } from '@/composables/useDate'
  const { format } = useCurrency()
  const { t } = useI18n()
  const { formatShortDate: fmtDate } = useDate()

  const poStore = usePurchaseOrderStore()
  const supplierStore = useSupplierStore()
  const { notif } = useAppUtils()

  const dialog = ref(false)
  const detailDialog = ref(false)
  const receiveDialog = ref(false)
  const cancelDialog = ref(false)
  const selectedPO = ref(null)
  const receivingPO = ref(null)
  const cancelTarget = ref(null)
  const saving = ref(false)
  const receiving = ref(false)
  const cancelling = ref(false)
  const tableRef = ref(null)

  const filters = ref({
    status: null,
    supplier_id: null
  })

  // ── Table headers ─────────────────────────────────────────────────────────────
  const headers = [
    { title: t('po.table.po_number'), key: 'po_number', sortable: false },
    { title: t('po.table.supplier'), key: 'supplier', sortable: false },
    { title: t('po.table.items'), key: 'items', sortable: false },
    { title: t('po.table.total'), key: 'total_amount', sortable: true },
    { title: t('po.table.expected'), key: 'expected_delivery', sortable: false },
    { title: t('po.table.status'), key: 'status', sortable: false },
    { title: t('po.table.date'), key: 'created_at', sortable: true },
    { title: '', key: 'actions', sortable: false, width: '120' }
  ]

  // ── Options ───────────────────────────────────────────────────────────────────
  const statusOptions = [
    { value: null, label: t('status.all') },
    { value: 'draft', label: t('po.status.draft') },
    { value: 'submitted', label: t('po.status.submitted') },
    { value: 'confirmed', label: t('po.status.confirmed') },
    { value: 'partially_received', label: t('po.status.partially_received') },
    { value: 'received', label: t('po.status.received') },
    { value: 'cancelled', label: t('po.status.cancelled') }
  ]

  // ── Helpers ───────────────────────────────────────────────────────────────────

  const statusColor = s =>
    ({
      draft: 'grey',
      submitted: 'info',
      confirmed: 'primary',
      partially_received: 'warning',
      received: 'success',
      cancelled: 'error'
    })[s] ?? 'grey'

  const statusIcon = s =>
    ({
      draft: 'mdi-file-outline',
      submitted: 'mdi-send-outline',
      confirmed: 'mdi-check',
      partially_received: 'mdi-package-variant',
      received: 'mdi-package-variant-closed-check',
      cancelled: 'mdi-cancel'
    })[s] ?? 'mdi-file'

  const statusLabel = s => (s ? t(`po.status.${s}`) : '')

  const canEdit = o => ['draft', 'submitted'].includes(o.status)
  const canSubmit = o => o.status === 'draft'
  const canConfirm = o => o.status === 'submitted'
  const canReceive = o => ['confirmed', 'partially_received'].includes(o.status)
  const canCancel = o => !['received', 'cancelled'].includes(o.status)

  const fmt = v => format(v)

  // ── Filters passed straight through to fetchTableData — AppTable deep-watches
  // this and refetches (resetting to page 1) whenever it changes. `search` is
  // deliberately absent here — it comes from AppTable's own built-in search
  // box, which spreads `filters` after its own `search` key so an explicit
  // (even undefined) `search` here would clobber it. ───────────────────────────
  const appliedFilters = computed(() => ({
    status: filters.value.status || undefined,
    supplier_id: filters.value.supplier_id || undefined
  }))

  async function fetchTableData(params) {
    await poStore.fetchPurchaseOrders(params)
    return { items: poStore.purchaseOrders ?? [], total: poStore.pagination?.total ?? 0 }
  }

  // ── Actions ───────────────────────────────────────────────────────────────────
  const openCreate = () => {
    selectedPO.value = null
    dialog.value = true
  }
  const openEdit = po => {
    selectedPO.value = po
    dialog.value = true
  }
  const openDetail = po => {
    selectedPO.value = po
    detailDialog.value = true
  }
  const openReceive = po => {
    receivingPO.value = po
    receiveDialog.value = true
  }
  const openReceiveFromDetail = po => {
    detailDialog.value = false
    receivingPO.value = po
    receiveDialog.value = true
  }

  const handleSave = async payload => {
    saving.value = true
    try {
      if (payload.id) {
        await poStore.updatePurchaseOrder(payload.id, payload)
        notif(t('po.messages.updated'), { type: 'success' })
      } else {
        await poStore.createPurchaseOrder(payload)
        notif(t('po.messages.created'), { type: 'success' })
      }
      dialog.value = false
      tableRef.value?.refresh()
    } catch {
      notif(t('po.messages.save_failed'), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  const handleReceive = async payload => {
    receiving.value = true
    try {
      await poStore.receivePurchaseOrder(receivingPO.value.id, payload)
      notif(t('po.messages.received'), { type: 'success' })
      receiveDialog.value = false
      tableRef.value?.refresh()
    } catch {
      notif(t('po.messages.receive_failed'), { type: 'error' })
    } finally {
      receiving.value = false
    }
  }

  const doSubmit = async po => {
    try {
      await poStore.submitPurchaseOrder(po.id)
      notif(t('po.messages.submitted'), { type: 'success' })
      tableRef.value?.refresh()
    } catch {
      notif(t('po.messages.submit_failed'), { type: 'error' })
    }
  }

  const doConfirm = async po => {
    try {
      await poStore.confirmPurchaseOrder(po.id)
      notif(t('po.messages.confirmed'), { type: 'success' })
      tableRef.value?.refresh()
    } catch {
      notif(t('po.messages.confirm_failed'), { type: 'error' })
    }
  }

  const confirmCancel = po => {
    cancelTarget.value = po
    cancelDialog.value = true
  }
  const doCancel = async () => {
    cancelling.value = true
    try {
      await poStore.cancelPurchaseOrder(cancelTarget.value.id)
      notif(t('po.messages.cancelled'), { type: 'success' })
      cancelDialog.value = false
      tableRef.value?.refresh()
    } catch {
      notif(t('po.messages.cancel_failed'), { type: 'error' })
    } finally {
      cancelling.value = false
    }
  }

  onMounted(() => {
    supplierStore.fetchSuppliers?.({ perPage: -1 })
  })
</script>
