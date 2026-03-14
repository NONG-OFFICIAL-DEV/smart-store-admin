<template>
  <div>
    <!-- Header -->
    <custom-title
      icon="mdi-cart-arrow-down"
      title="Purchase Orders"
      subtitle="Mart stock replenishment"
    >
      <template #right>
        <!-- <v-btn
          variant="tonal"
          rounded="lg"
          prepend-icon="mdi-alert-circle-outline"
          color="warning"
          :to="{ name: 'MartLowStock' }"
        >
          Low Stock
        </v-btn> -->
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
              placeholder="Search PO number or supplier..."
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
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Table -->
    <v-card rounded="lg" border elevation="0">
      <v-data-table-server
        :headers="headers"
        :items="poStore.orders"
        :items-length="poStore.pagination?.total ?? 0"
        :loading="poStore.loading"
        :items-per-page="filters.per_page"
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
            {{ item.items_count ?? item.items?.length ?? 0 }} items
          </v-chip>
        </template>

        <!-- Total -->
        <template #item.total_amount="{ item }">
          <span class="font-weight-bold">{{ fmt(item.total_amount) }}</span>
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
              title="Receive Stock"
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
      </v-data-table-server>
    </v-card>

    <!-- Create/Edit Dialog -->
    <!-- <MartPoDialog
      v-model="dialog"
      :po="selectedPo"
      :loading="saving"
      @save="handleSave"
    /> -->

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
    <v-dialog v-model="cancelDialog" max-width="400">
      <v-card rounded="xl" border elevation="0">
        <v-card-title class="pa-5">
          <div class="d-flex align-center gap-3">
            <v-avatar color="warning" variant="tonal" size="40" rounded="lg">
              <v-icon icon="mdi-close-circle-outline" />
            </v-avatar>
            <div>
              <div class="text-body-1 font-weight-bold">Cancel PO?</div>
              <div class="text-caption text-medium-emphasis">
                {{ cancelTarget?.po_number }}
              </div>
            </div>
          </div>
        </v-card-title>
        <v-card-actions class="pa-5 gap-3">
          <v-btn variant="tonal" rounded="lg" @click="cancelDialog = false">
            Back
          </v-btn>
          <v-btn
            color="warning"
            variant="flat"
            rounded="lg"
            :loading="cancelling"
            @click="doCancel"
          >
            Cancel PO
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useMartPurchaseOrderStore } from '@/stores/martPurchaseOrderStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  //   import MartPoDialog from '@/components/mart/MartPoDialog.vue'
  import MartPoReceiveDialog from '@/components/mart/MartPoReceiveDialog.vue'
  import MartPoDetailDialog from '@/components/mart/MartPoDetailDialog.vue'
  import { useRouter } from 'vue-router'
  const poStore = useMartPurchaseOrderStore()
  const { notif, confirm } = useAppUtils()

  const router = useRouter()
  const receiveDialog = ref(false)
  const detailDialog = ref(false)
  const cancelDialog = ref(false)
  const selectedPo = ref(null)
  const detailPo = ref(null)
  const cancelTarget = ref(null)
  const receiving = ref(false)
  const cancelling = ref(false)

  const filters = ref({
    branch_id: null,
    search: '',
    status: null,
    per_page: 15,
    page: 1
  })

  const statusOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'submitted', label: 'Submitted' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'partially_received', label: 'Partially Received' },
    { value: 'received', label: 'Received' },
    { value: 'cancelled', label: 'Cancelled' }
  ]

  const headers = [
    { title: 'PO Number', key: 'po_number', sortable: false },
    { title: 'Supplier', key: 'supplier', sortable: false },
    { title: 'Items', key: 'items_count', sortable: false },
    { title: 'Total', key: 'total_amount', sortable: false },
    { title: 'Expected', key: 'expected_delivery', sortable: false },
    { title: 'Status', key: 'status', sortable: false },
    { title: '', key: 'actions', sortable: false, width: '160' }
  ]

  // Replace these two methods:
  const openCreate = () => router.push({ name: 'MartPurchaseOrderCreate' })
  const openEdit = po =>
    router.push({ name: 'MartPurchaseOrderEdit', params: { id: po.id } })

  // Then remove: dialog ref, MartPoDialog import, handleSave, saving
  const stats = computed(() => {
    const orders = poStore.orders
    return [
      {
        label: 'Total POs',
        value: poStore.pagination?.total ?? 0,
        color: 'primary'
      },
      {
        label: 'Draft',
        value: orders.filter(o => o.status === 'draft').length,
        color: 'grey'
      },
      {
        label: 'In Progress',
        value: orders.filter(o =>
          ['submitted', 'confirmed', 'partially_received'].includes(o.status)
        ).length,
        color: 'warning'
      },
      {
        label: 'Received',
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
  const canReceive = o =>
    ['confirmed', 'partially_received', 'submitted'].includes(o.status)
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

  let searchTimer = null
  const onSearch = () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(load, 400)
  }
  const load = () => poStore.fetchOrders(filters.value)

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
      notif(res.message ?? 'Stock received', { type: 'success' })
      receiveDialog.value = false
      load()
    } catch (e) {
      notif(e.response?.data?.message ?? 'Failed to receive', { type: 'error' })
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
      notif('PO cancelled', { type: 'success' })
      cancelDialog.value = false
      load()
    } catch (e) {
      notif(e.response?.data?.message ?? 'Failed', { type: 'error' })
    } finally {
      cancelling.value = false
    }
  }

  const confirmDelete = async po => {
    try {
      confirm({
        title: 'Delete PO?',
        message: `${po.po_number}`,
        options: { type: 'warning', width: 500 },
        agree: async () => {
          await poStore.deleteOrder(po.id)
          notif('PO deleted', { type: 'success' })
        },
        cancel: () => {}
      })
    } catch (e) {
      notif(e.response?.data?.message ?? 'Failed', { type: 'error' })
    }
  }

  onMounted(load)
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
