<template>
  <div>
    <custom-title
      :title="$t('po.title')"
      icon="mdi-cart-arrow-down"
      :subtitle="$t('po.subtitle')"
    >
      <template #right>
        <v-btn
          :color="showFilters ? 'primary' : 'default'"
          :variant="showFilters ? 'flat' : 'tonal'"
          rounded="lg"
          :prepend-icon="
            showFilters ? 'mdi-filter-off-outline' : 'mdi-filter-outline'
          "
          @click="showFilters = !showFilters"
        >
          {{ $t('btn.filter') }}
          <v-badge
            v-if="activeFilterCount > 0"
            :content="activeFilterCount"
            color="error"
            floating
          />
        </v-btn>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          class="ms-2"
          @click="openCreate"
        >
          {{ $t('btn.create_po') }}
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
    <v-expand-transition>
      <v-card v-if="showFilters" rounded="xl" elevation="0" class="mb-4">
        <v-card-text>
          <v-row dense align="center">
            <v-col cols="12" sm="4">
              <v-text-field
                v-model="filters.search"
                :placeholder="$t('placeholder.search_po')"
                variant="outlined"
                rounded="lg"
                hide-details
                clearable
                prepend-inner-icon="mdi-magnify"
                @update:model-value="onSearch"
                @keyup.enter="onFilterChange"
              />
            </v-col>
            <v-col cols="6" sm="2">
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
                @update:model-value="load"
              />
            </v-col>
            <v-col cols="6" sm="2">
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
                @update:model-value="load"
              />
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions class="px-4">
          <v-spacer />
          <v-btn
            v-if="hasActiveFilters"
            rounded="lg"
            variant="tonal"
            color="error"
            prepend-icon="mdi-close"
            @click="resetFilters"
          >
            {{ $t('btn.reset') }}
          </v-btn>
          <v-btn
            class="bg-primary"
            rounded="lg"
            prepend-icon="mdi-magnify"
            @click="onFilterChange"
          >
            {{ $t('btn.search') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-expand-transition>
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
    <AppDialog
      v-model="cancelDialog"
      :max-width="400"
      :title="$t('po.confirm_cancel.title')"
      :subtitle="cancelTarget?.po_number"
      icon="mdi-cancel"
      color="error"
      :loading="cancelling"
      :cancel-text="$t('po.confirm_cancel.keep')"
      :submit-text="$t('po.confirm_cancel.confirm')"
      @submit="doCancel"
    >
      {{ $t('po.confirm_cancel.message') }}
    </AppDialog>
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
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import { useI18n } from 'vue-i18n'
  import AppDialog from '@/components/common/AppDialog.vue'
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
  const showFilters = ref(false)

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
        label: t('purchase_order.summary.total'),
        value: poStore.pagination?.total ?? 0,
        color: 'primary',
        icon: 'mdi-clipboard-list-outline'
      },
      {
        label: t('purchase_order.summary.draft'),
        value: orders.filter(o => o.status === 'draft').length,
        color: 'grey',
        icon: 'mdi-file-outline'
      },
      {
        label: t('status.pending'),
        value: orders.filter(o => ['submitted', 'confirmed'].includes(o.status))
          .length,
        color: 'warning',
        icon: 'mdi-clock-outline'
      },
      {
        label: t('purchase_order.summary.received'),
        value: orders.filter(o => o.status === 'received').length,
        color: 'success',
        icon: 'mdi-check-circle-outline'
      }
    ]
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

  // ── Active filter badge ───────────────────────────────────────────────────────
  const activeFilterCount = computed(() => {
    let count = 0
    if (filters.value.search?.trim()) count++
    if (filters.value.status) count++
    if (filters.value.supplier_id) count++
    return count
  })
  const hasActiveFilters = computed(() => activeFilterCount.value > 0)

  // ── Actions ───────────────────────────────────────────────────────────────────
  let searchTimer = null
  const onSearch = () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => load(), 400)
  }

  const load = () => poStore.fetchPurchaseOrders(filters.value)

  // ── Reset to page 1 and reload when filters change ────────────────────────────
  const onFilterChange = () => {
    filters.value.page = 1
    load()
  }

  const resetFilters = () => {
    filters.value.search = ''
    filters.value.status = null
    filters.value.supplier_id = null
    filters.value.page = 1
    load()
  }

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
    } catch {
      notif(t('po.messages.submit_failed'), { type: 'error' })
    }
  }

  const doConfirm = async po => {
    try {
      await poStore.confirmPurchaseOrder(po.id)
      notif(t('po.messages.confirmed'), { type: 'success' })
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
    } catch {
      notif(t('po.messages.cancel_failed'), { type: 'error' })
    } finally {
      cancelling.value = false
    }
  }

  onMounted(() => {
    load()
    supplierStore.fetchSuppliers?.()
  })
</script>
