<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-receipt-text-outline"
      :title="t('orders_admin.title')"
      :subtitle="t('orders_admin.subtitle')"
    />

    <v-row v-if="stats" dense class="mb-4">
      <v-col cols="6" sm="3">
        <v-card rounded="lg" border elevation="0" class="pa-4">
          <div class="text-caption text-medium-emphasis">{{ t('orders_admin.stats.total_orders') }}</div>
          <div class="text-h6 font-weight-bold">{{ stats.total_orders }}</div>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card rounded="lg" border elevation="0" class="pa-4">
          <div class="text-caption text-medium-emphasis">{{ t('orders_admin.stats.total_revenue') }}</div>
          <div class="text-h6 font-weight-bold">{{ formatMoney(stats.total_revenue) }}</div>
        </v-card>
      </v-col>
    </v-row>

    <v-row dense align="center" class="mb-2">
      <v-col cols="6" sm="3">
        <v-select
          v-model="filters.status"
          :items="statusOptions"
          item-title="label"
          item-value="value"
          :label="t('orders_admin.filters.status')"
          variant="outlined"
          rounded="lg"
          clearable
        />
      </v-col>
      <v-col cols="6" sm="3">
        <v-select
          v-model="filters.order_type"
          :items="orderTypeOptions"
          item-title="label"
          item-value="value"
          :label="t('orders_admin.filters.order_type')"
          variant="outlined"
          rounded="lg"
          clearable
        />
      </v-col>
      <v-col cols="6" sm="3">
        <v-text-field
          v-model="filters.date_from"
          type="date"
          :label="t('orders_admin.filters.date_from')"
          variant="outlined"
          rounded="lg"
          clearable
        />
      </v-col>
      <v-col cols="6" sm="3">
        <v-text-field
          v-model="filters.date_to"
          type="date"
          :label="t('orders_admin.filters.date_to')"
          variant="outlined"
          rounded="lg"
          clearable
        />
      </v-col>
    </v-row>

    <v-card rounded="lg" elevation="0" border class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchOrdersForTable"
        :filters="filters"
        :show-search="true"
        :item-label="t('orders_admin.title')"
      >
        <template #item.order_number="{ item }">
          <div class="text-body-2 font-weight-medium">{{ item.order_number }}</div>
          <div class="text-caption text-medium-emphasis">{{ formatDateTime(item.created_at) }}</div>
        </template>

        <template #item.customer="{ item }">
          <span class="text-body-2">{{ item.customer?.name ?? t('orders_admin.walk_in') }}</span>
        </template>

        <template #item.order_type="{ item }">
          <span class="text-body-2 text-capitalize">{{ (item.order_type ?? '').replace('_', ' ') }}</span>
        </template>

        <template #item.items_count="{ item }">
          <span class="text-body-2">{{ item.items?.length ?? 0 }}</span>
        </template>

        <template #item.total_amount="{ item }">
          <span class="text-body-2 font-weight-bold">{{ formatMoney(item.total_amount) }}</span>
        </template>

        <template #item.status="{ item }">
          <AppStatusChip :status="item.status" :map="statusMap" size="small" />
        </template>

        <template #item.payment_method="{ item }">
          <span class="text-body-2 text-uppercase">
            {{ item.payments?.[0]?.payment_method ?? item.payment_method ?? '—' }}
          </span>
        </template>

        <template #item.actions="{ item }">
          <v-btn icon="mdi-eye-outline" size="small" variant="text" @click="viewOrder(item)" />
        </template>

        <template #no-data>
          <div class="text-center py-10">
            <v-icon icon="mdi-receipt-text-outline" size="48" color="grey-lighten-1" class="mb-2" />
            <p class="text-body-2 text-medium-emphasis">{{ t('orders_admin.empty') }}</p>
          </div>
        </template>
      </AppTable>
    </v-card>

    <AppDialog
      v-model="detailDialog"
      :title="detailOrder?.order_number"
      icon="mdi-receipt-text-outline"
      :max-width="440"
      hide-submit
      :cancel-text="t('btn.close')"
      :loading="detailLoading"
      @close="detailDialog = false"
    >
      <template v-if="detailOrder">
        <div class="d-flex justify-space-between text-body-2 mb-2">
          <span class="text-medium-emphasis">{{ t('orders_admin.detail.status') }}</span>
          <AppStatusChip :status="detailOrder.status" :map="statusMap" size="small" />
        </div>
        <div v-if="detailOrder.table" class="d-flex justify-space-between text-body-2 mb-2">
          <span class="text-medium-emphasis">{{ t('orders_admin.detail.table') }}</span>
          <span>{{ detailOrder.table.number }}</span>
        </div>
        <v-divider class="my-2" />
        <div v-for="item in detailOrder.items" :key="item.id" class="d-flex justify-space-between text-body-2 mb-1">
          <span>{{ item.quantity }} × {{ item.product_name }}</span>
          <span class="font-weight-medium">{{ formatMoney(item.total_price) }}</span>
        </div>
        <v-divider class="my-2" />
        <div class="d-flex justify-space-between text-body-1 font-weight-bold">
          <span>{{ t('orders_admin.detail.total') }}</span>
          <span>{{ formatMoney(detailOrder.total_amount) }}</span>
        </div>
      </template>
    </AppDialog>
  </v-container>
</template>

<script setup>
  import { ref, reactive } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { AppTable, AppStatusChip } from '@nong-official-dev/core'
  import { useOrderStore } from '@/stores/orderStore'
  import { getAllOrdersReportApi } from '@/api/orderService'
  import { useDate } from '@/composables/useDate'
  import AppDialog from '@/components/common/AppDialog.vue'

  const { t } = useI18n()
  const orderStore = useOrderStore()
  const { formatDateTime } = useDate()

  const tableRef = ref(null)
  const stats = ref(null)

  const filters = reactive({
    status: null,
    order_type: null,
    date_from: null,
    date_to: null
  })

  const statusOptions = [
    'draft', 'pending', 'confirmed', 'preparing', 'ready', 'served', 'completed', 'cancelled', 'refunded'
  ].map(value => ({ value, label: t(`orders_admin.status.${value}`) }))

  const orderTypeOptions = ['dine_in', 'takeaway', 'delivery', 'online'].map(value => ({
    value,
    label: t(`orders_admin.order_type.${value}`)
  }))

  const statusMap = {
    draft: { color: 'grey', label: t('orders_admin.status.draft') },
    pending: { color: 'warning', label: t('orders_admin.status.pending') },
    confirmed: { color: 'info', label: t('orders_admin.status.confirmed') },
    preparing: { color: 'info', label: t('orders_admin.status.preparing') },
    ready: { color: 'success', label: t('orders_admin.status.ready') },
    served: { color: 'success', label: t('orders_admin.status.served') },
    completed: { color: 'success', label: t('orders_admin.status.completed') },
    cancelled: { color: 'error', label: t('orders_admin.status.cancelled') },
    refunded: { color: 'error', label: t('orders_admin.status.refunded') }
  }

  const headers = [
    { title: t('orders_admin.columns.order'), key: 'order_number', sortable: false },
    { title: t('orders_admin.columns.customer'), key: 'customer', sortable: false },
    { title: t('orders_admin.columns.type'), key: 'order_type', sortable: false },
    { title: t('orders_admin.columns.items'), key: 'items_count', sortable: false, align: 'center' },
    { title: t('orders_admin.columns.total'), key: 'total_amount', sortable: false, align: 'end' },
    { title: t('orders_admin.columns.payment'), key: 'payment_method', sortable: false },
    { title: t('orders_admin.columns.status'), key: 'status', sortable: false },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ]

  // orderReport() is legacy (deferred `Order` model, per this repo's own
  // CLAUDE.md) and pre-dates the perPage/sortBy camelCase convention other
  // migrated resources use — it reads per_page/page directly and has no
  // sort support at all, hence every header above is sortable:false.
  async function fetchOrdersForTable(params) {
    const { data } = await getAllOrdersReportApi({
      page: params.page,
      per_page: params.perPage,
      search: params.search,
      status: params.status,
      order_type: params.order_type,
      date_from: params.date_from,
      date_to: params.date_to
    })
    stats.value = data.stats
    return { items: data.data.data, total: data.data.total }
  }

  const detailDialog = ref(false)
  const detailOrder = ref(null)
  const detailLoading = ref(false)

  async function viewOrder(item) {
    detailDialog.value = true
    detailLoading.value = true
    detailOrder.value = null
    try {
      const res = await orderStore.fetchOrderById(item.id)
      detailOrder.value = res.data.data
    } finally {
      detailLoading.value = false
    }
  }

  function formatMoney(value) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value ?? 0)
  }
</script>
