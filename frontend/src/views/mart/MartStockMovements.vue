<template>
  <div>
    <!-- Header -->
    <AppPageHeader
      title="Stock Movements"
      show-back
      :breadcrumbs="[{ title: 'Stocks', to: '/mart/stock' }]"
    >
      <!-- { title: product.name } -->
      <template #title-after>
        <v-chip color="success" size="x-small" variant="flat">
          Full stock ledger history
        </v-chip>
      </template>

      <template #right>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-tune"
          @click="adjustDialog = true"
        >
          Adjust Stock
        </v-btn>
      </template>
    </AppPageHeader>
    <!-- <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h5 font-weight-bold">Stock Movements</h1>
        <p class="text-caption text-medium-emphasis mt-1">
          Full stock ledger history
        </p>
      </div>
      <v-btn
        color="primary"
        variant="flat"
        rounded="lg"
        prepend-icon="mdi-tune"
        @click="adjustDialog = true"
      >
        Adjust Stock
      </v-btn>
    </div> -->

    <!-- Summary cards -->
    <v-row dense class="mb-5">
      <v-col v-for="s in summaryCards" :key="s.label" cols="6" sm="3">
        <v-card rounded="xl" border elevation="0" class="pa-4">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption text-medium-emphasis">{{ s.label }}</span>
            <v-avatar size="28" :color="s.color" variant="tonal" rounded="lg">
              <v-icon :icon="s.icon" size="14" />
            </v-avatar>
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
          <!-- Product filter -->
          <v-col cols="12" sm="3">
            <v-autocomplete
              v-model="filters.product_id"
              :items="martProductStore.products"
              item-title="name"
              item-value="id"
              placeholder="Filter by product"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
              @update:model-value="load"
            />
          </v-col>

          <!-- Type filter -->
          <v-col cols="6" sm="2">
            <v-select
              v-model="filters.movement_type"
              :items="typeOptions"
              item-title="label"
              item-value="value"
              placeholder="Type"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
              @update:model-value="load"
            />
          </v-col>

          <!-- Date from -->
          <v-col cols="6" sm="2">
            <v-text-field
              v-model="filters.from"
              type="date"
              label="From"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              @update:model-value="load"
            />
          </v-col>

          <!-- Date to -->
          <v-col cols="6" sm="2">
            <v-text-field
              v-model="filters.to"
              type="date"
              label="To"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              @update:model-value="load"
            />
          </v-col>

          <!-- Clear -->
          <v-col cols="6" sm="1">
            <v-btn
              variant="tonal"
              rounded="lg"
              block
              :disabled="!hasFilters"
              @click="clearFilters"
            >
              Clear
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Table -->
    <v-card rounded="lg" border elevation="0">
      <v-data-table-server
        :headers="headers"
        :items="movements"
        :items-length="pagination?.total ?? 0"
        :loading="loading"
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
        <!-- Date -->
        <template #item.created_at="{ item }">
          <div class="text-body-2">{{ fmtDate(item.created_at) }}</div>
          <div class="text-caption text-medium-emphasis">
            {{ fmtTime(item.created_at) }}
          </div>
        </template>

        <!-- Product -->
        <template #item.product="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <v-avatar
              size="34"
              rounded="lg"
              class="border flex-shrink-0 bg-grey-lighten-4"
            >
              <v-img
                v-if="item.product?.image_url"
                :src="item.product.image_url"
                cover
              />
              <v-icon
                v-else
                icon="mdi-package-variant"
                size="16"
                color="grey"
              />
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-medium">
                {{ item.product?.name ?? '—' }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.product?.sku ?? '' }}
              </div>
            </div>
          </div>
        </template>

        <!-- Type -->
        <template #item.movement_type="{ item }">
          <v-chip
            size="small"
            rounded="lg"
            variant="tonal"
            :color="typeColor(item.movement_type)"
          >
            <v-icon start size="11" :icon="typeIcon(item.movement_type)" />
            {{ typeLabel(item.movement_type) }}
          </v-chip>
        </template>

        <!-- Quantity -->
        <template #item.quantity="{ item }">
          <span
            class="font-weight-black text-body-2"
            :class="item.quantity > 0 ? 'text-success' : 'text-error'"
          >
            {{ item.quantity > 0 ? '+' : '' }}{{ item.quantity }}
          </span>
        </template>

        <!-- Before → After -->
        <template #item.qty_before="{ item }">
          <div class="d-flex align-center gap-1 text-body-2">
            <span class="text-medium-emphasis">{{ item.qty_before }}</span>
            <v-icon icon="mdi-arrow-right" size="12" color="grey" />
            <span class="font-weight-bold">{{ item.qty_after }}</span>
          </div>
        </template>

        <!-- Reference -->
        <template #item.reference="{ item }">
          <div v-if="item.reference_type" class="text-caption">
            <v-chip size="x-small" rounded="lg" variant="tonal" color="grey">
              {{ item.reference_type }}
            </v-chip>
          </div>
          <span v-else class="text-caption text-medium-emphasis">—</span>
        </template>

        <!-- Staff -->
        <template #item.staff="{ item }">
          <span class="text-caption">
            {{ item.staff?.user?.first_name ?? '—' }}
          </span>
        </template>

        <!-- Notes -->
        <template #item.notes="{ item }">
          <span
            class="text-caption text-medium-emphasis text-truncate"
            style="max-width: 160px; display: block"
          >
            {{ item.notes ?? '—' }}
          </span>
        </template>

        <!-- Empty -->
        <template #no-data>
          <div class="text-center py-12">
            <v-icon
              icon="mdi-history"
              size="48"
              color="grey-lighten-2"
              class="mb-3"
            />
            <div class="text-body-2 font-weight-medium text-grey">
              No movements found
            </div>
            <p class="text-caption text-grey mt-1">
              Try adjusting your filters
            </p>
          </div>
        </template>
      </v-data-table-server>
    </v-card>

    <!-- Adjust dialog -->
    <StockAdjustDialog
      v-model="adjustDialog"
      :loading="adjusting"
      @save="handleAdjust"
    />
  </div>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useRoute } from 'vue-router'
  import { useMartProductStore } from '@/stores/martProductStore'
  import { useAuthStore } from '@/stores/authStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import { getMovementsApi, adjustStockApi } from '@/api/martStockService'
  import StockAdjustDialog from '@/components/mart/StockAdjustDialog.vue'
  import AppPageHeader from '@/components/customs/AppPageHeader.vue'

  const route = useRoute()
  const martProductStore = useMartProductStore()
  const { product } = storeToRefs(martProductStore)

  const authStore = useAuthStore()
  const { notif } = useAppUtils()

  const movements = ref([])
  const pagination = ref(null)
  const loading = ref(false)
  const adjustDialog = ref(false)
  const adjusting = ref(false)

  const filters = ref({
    product_id: route.query.product_id ?? null,
    movement_type: null,
    from: null,
    to: null,
    per_page: 20,
    page: 1
  })

  // ── Type config ───────────────────────────────────────────────────────────
  const typeOptions = [
    { value: 'purchase', label: 'Purchase' },
    { value: 'sale', label: 'Sale' },
    { value: 'adjustment_in', label: 'Adjustment In' },
    { value: 'adjustment_out', label: 'Adjustment Out' },
    { value: 'waste', label: 'Waste' },
    { value: 'count', label: 'Stock Count' },
    { value: 'transfer_in', label: 'Transfer In' },
    { value: 'transfer_out', label: 'Transfer Out' }
  ]

  const typeColorMap = {
    purchase: 'success',
    sale: 'primary',
    adjustment_in: 'teal',
    adjustment_out: 'orange',
    waste: 'error',
    count: 'purple',
    transfer_in: 'cyan',
    transfer_out: 'blue-grey'
  }

  const typeIconMap = {
    purchase: 'mdi-package-down',
    sale: 'mdi-cart-outline',
    adjustment_in: 'mdi-plus-circle-outline',
    adjustment_out: 'mdi-minus-circle-outline',
    waste: 'mdi-trash-can-outline',
    count: 'mdi-clipboard-check-outline',
    transfer_in: 'mdi-transfer-right',
    transfer_out: 'mdi-transfer-left'
  }

  const typeColor = t => typeColorMap[t] ?? 'grey'
  const typeIcon = t => typeIconMap[t] ?? 'mdi-circle-small'
  const typeLabel = t => typeOptions.find(o => o.value === t)?.label ?? t

  // ── Table headers ─────────────────────────────────────────────────────────
  const headers = [
    { title: 'Date', key: 'created_at', sortable: false, width: '130' },
    { title: 'Product', key: 'product', sortable: false },
    { title: 'Type', key: 'movement_type', sortable: false, width: '160' },
    { title: 'Qty', key: 'quantity', sortable: false, width: '80' },
    {
      title: 'Before → After',
      key: 'qty_before',
      sortable: false,
      width: '130'
    },
    { title: 'Reference', key: 'reference', sortable: false, width: '110' },
    { title: 'Staff', key: 'staff', sortable: false, width: '100' },
    { title: 'Notes', key: 'notes', sortable: false }
  ]

  // ── Summary cards ─────────────────────────────────────────────────────────
  const summaryCards = computed(() => {
    const all = movements.value
    return [
      {
        label: 'Total Entries',
        icon: 'mdi-history',
        color: 'primary',
        value: pagination.value?.total ?? all.length
      },
      {
        label: 'Sales',
        icon: 'mdi-cart-outline',
        color: 'blue',
        value: all.filter(m => m.movement_type === 'sale').length
      },
      {
        label: 'Purchases',
        icon: 'mdi-package-down',
        color: 'success',
        value: all.filter(m => m.movement_type === 'purchase').length
      },
      {
        label: 'Adjustments',
        icon: 'mdi-tune',
        color: 'warning',
        value: all.filter(m =>
          ['adjustment_in', 'adjustment_out', 'waste', 'count'].includes(
            m.movement_type
          )
        ).length
      }
    ]
  })

  // ── Helpers ───────────────────────────────────────────────────────────────
  const hasFilters = computed(
    () =>
      filters.value.product_id ||
      filters.value.movement_type ||
      filters.value.from ||
      filters.value.to
  )

  const fmtDate = v =>
    new Date(v).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    })
  const fmtTime = v =>
    new Date(v).toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit'
    })

  const clearFilters = () => {
    filters.value.product_id = null
    filters.value.movement_type = null
    filters.value.from = null
    filters.value.to = null
    filters.value.page = 1
    load()
  }

  // ── Data fetch ────────────────────────────────────────────────────────────
  const load = async () => {
    loading.value = true
    try {
      const res = await getMovementsApi({
        branch_id: authStore.branch_id,
        ...filters.value
      })
      movements.value = res.data.data.data ?? res.data.data
      pagination.value = res.data.data
    } catch (e) {
      notif('Failed to load movements', { type: 'error' })
    } finally {
      loading.value = false
    }
  }

  // ── Adjust stock ──────────────────────────────────────────────────────────
  const handleAdjust = async payload => {
    adjusting.value = true
    try {
      await adjustStockApi({ branch_id: authStore.branch_id, ...payload })
      notif('Stock adjusted', { type: 'success' })
      adjustDialog.value = false
      martProductStore.fetchProducts(true)
      load()
    } catch (e) {
      notif(e.response?.data?.message ?? 'Failed', { type: 'error' })
    } finally {
      adjusting.value = false
    }
  }

  onMounted(() => {
    load()
    martProductStore.fetchProducts()
  })
</script>

<style scoped>
  .gap-1 {
    gap: 4px;
  }
  .gap-2 {
    gap: 8px;
  }
</style>
