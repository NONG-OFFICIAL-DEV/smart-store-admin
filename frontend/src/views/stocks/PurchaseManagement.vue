<template>
  <div>
    <custom-title
      title="Purchase Orders"
      subtitle=" Manage supplier orders and stock receiving"
    >
      <template #right>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          @click="openCreate"
        >
          New PO
        </v-btn>
      </template>
    </custom-title>

    <!-- ── Stats ──────────────────────────────────────────────────────────── -->
    <v-row dense class="mb-4">
      <v-col v-for="s in statCards" :key="s.label" cols="6" sm="3">
        <v-card rounded="lg" border elevation="0" class="pa-4">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption text-medium-emphasis">{{ s.label }}</span>
            <v-icon :icon="s.icon" :color="s.color" size="18" />
          </div>
          <div class="text-h6 font-weight-bold" :class="`text-${s.color}`">
            {{ s.value }}
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Filters ────────────────────────────────────────────────────────── -->
    <v-card rounded="lg" border elevation="0" class="mb-4">
      <v-card-text class="pa-4">
        <v-row dense align="center">
          <v-col cols="12" sm="4">
            <v-text-field
              v-model="filters.search"
              placeholder="Search PO number..."
              variant="outlined"
              density="compact"
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
              placeholder="Status"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
              @update:model-value="load"
            />
          </v-col>
          <v-col cols="6" sm="2">
            <v-select
              v-model="filters.supplier_id"
              :items="supplierStore.suppliers.data"
              item-title="name"
              item-value="id"
              placeholder="Supplier"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
              @update:model-value="load"
            />
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>
    <!-- ── Table ──────────────────────────────────────────────────────────── -->
    <v-card rounded="lg" border elevation="0">
      <v-data-table-server
        :headers="headers"
        :items="poStore.purchaseOrders"
        :items-length="poStore.pagination?.total ?? 0"
        :loading="poStore.loading"
        :items-per-page="filters.per_page"
        :page="filters.page"
        item-value="id"
        @update:page="
          p => {
            filters.page = p
            load()
          }
        "
        @update:items-per-page="
          p => {
            filters.per_page = p
            filters.page = 1
            load()
          }
        "
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
            {{ item.items?.length ?? 0 }} items
          </v-chip>
        </template>

        <!-- Total -->
        <template #item.total_amount="{ item }">
          <span class="font-weight-bold">{{ fmt(item.total_amount) }}</span>
        </template>

        <!-- Expected -->
        <template #item.expected_delivery="{ item }">
          <span class="text-body-2">{{ item.expected_delivery ?? '—' }}</span>
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
              v-if="canReceive(item)"
              icon="mdi-package-down"
              size="small"
              variant="text"
              color="success"
              title="Receive items"
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
      </v-data-table-server>
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
    <v-dialog v-model="cancelDialog" max-width="400">
      <v-card rounded="xl" border elevation="0">
        <v-card-title class="pa-5">
          <div class="d-flex align-center gap-3">
            <v-avatar color="error" variant="tonal" size="40" rounded="lg">
              <v-icon icon="mdi-cancel" />
            </v-avatar>
            <div>
              <div class="text-body-1 font-weight-bold">Cancel PO?</div>
              <div class="text-caption text-medium-emphasis">
                {{ cancelTarget?.po_number }}
              </div>
            </div>
          </div>
        </v-card-title>
        <v-card-text class="px-5 pb-0">
          This action cannot be undone.
        </v-card-text>
        <v-card-actions class="pa-5 gap-3">
          <v-btn variant="tonal" rounded="lg" @click="cancelDialog = false">
            No, Keep
          </v-btn>
          <v-btn
            color="error"
            variant="flat"
            rounded="lg"
            :loading="cancelling"
            @click="doCancel"
          >
            Yes, Cancel
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { usePurchaseOrderStore } from '@/stores/purchaseOrderStore'
  import { useSupplierStore } from '@/stores/supplierStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import PurchaseOrderDialog from '@/components/purchase-orders/PurchaseOrderDialog.vue'
  import PurchaseOrderDetailDialog from '@/components/purchase-orders/PurchaseOrderDetailDialog.vue'
  import PurchaseOrderReceiveDialog from '@/components/purchase-orders/PurchaseOrderReceiveDialog.vue'

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

  const filters = ref({
    search: '',
    status: null,
    supplier_id: null,
    per_page: 15,
    page: 1
  })

  // ── Stats ─────────────────────────────────────────────────────────────────────
  const statCards = computed(() => {
    const orders = poStore.purchaseOrders
    return [
      {
        label: 'Total POs',
        value: poStore.pagination?.total ?? 0,
        color: 'primary',
        icon: 'mdi-clipboard-list-outline'
      },
      {
        label: 'Draft',
        value: orders.filter(o => o.status === 'draft').length,
        color: 'grey',
        icon: 'mdi-file-outline'
      },
      {
        label: 'Pending',
        value: orders.filter(o => ['submitted', 'confirmed'].includes(o.status))
          .length,
        color: 'warning',
        icon: 'mdi-clock-outline'
      },
      {
        label: 'Received',
        value: orders.filter(o => o.status === 'received').length,
        color: 'success',
        icon: 'mdi-check-circle-outline'
      }
    ]
  })

  // ── Table headers ─────────────────────────────────────────────────────────────
  const headers = [
    { title: 'PO Number', key: 'po_number', sortable: false },
    { title: 'Supplier', key: 'supplier', sortable: false },
    { title: 'Items', key: 'items', sortable: false },
    { title: 'Total', key: 'total_amount', sortable: true },
    { title: 'Expected', key: 'expected_delivery', sortable: false },
    { title: 'Status', key: 'status', sortable: false },
    { title: 'Date', key: 'created_at', sortable: true },
    { title: '', key: 'actions', sortable: false, width: '120' }
  ]

  // ── Options ───────────────────────────────────────────────────────────────────
  const statusOptions = [
    { value: null, label: 'All' },
    { value: 'draft', label: 'Draft' },
    { value: 'submitted', label: 'Submitted' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'partially_received', label: 'Partially Received' },
    { value: 'received', label: 'Received' },
    { value: 'cancelled', label: 'Cancelled' }
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

  const statusLabel = s => s?.replace('_', ' ') ?? ''

  const canEdit = o => ['draft', 'submitted'].includes(o.status)
  const canReceive = o => ['confirmed', 'partially_received'].includes(o.status)
  const canCancel = o => !['received', 'cancelled'].includes(o.status)

  const fmt = v =>
    new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(v ?? 0)
  const fmtDate = v =>
    v
      ? new Date(v).toLocaleDateString('en-US', {
          month: 'short',
          day: 'numeric',
          year: 'numeric'
        })
      : '—'

  // ── Actions ───────────────────────────────────────────────────────────────────
  let searchTimer = null
  const onSearch = () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => load(), 400)
  }

  const load = () => poStore.fetchPurchaseOrders(filters.value)

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
        notif('Purchase order updated', { type: 'success' })
      } else {
        await poStore.createPurchaseOrder(payload)
        notif('Purchase order created', { type: 'success' })
      }
      dialog.value = false
    } catch {
      notif('Failed to save purchase order', { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  const handleReceive = async payload => {
    receiving.value = true
    try {
      await poStore.receivePurchaseOrder(receivingPO.value.id, payload)
      notif('Items received and stock updated', { type: 'success' })
      receiveDialog.value = false
    } catch {
      notif('Failed to receive items', { type: 'error' })
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
      await poStore.cancelPurchaseOrder(cancelTarget.value.id)
      notif('Purchase order cancelled', { type: 'success' })
      cancelDialog.value = false
    } catch {
      notif('Failed to cancel', { type: 'error' })
    } finally {
      cancelling.value = false
    }
  }

  onMounted(() => {
    load()
    supplierStore.fetchSuppliers?.()
  })
</script>
