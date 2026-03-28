<template>
  <div>
    <custom-title
      icon="mdi-warehouse"
      title="Inventory & Stock Report"
      subtitle="Mange stock report"
    >
      <template #right>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-refresh"
          :loading="loading"
          @click="load"
        >
          Refresh
        </v-btn>
        <v-btn
          class="ms-2"
          variant="outlined"
          color="success"
          prepend-icon="mdi-file-excel"
        >
          Export Excel
        </v-btn>
      </template>
    </custom-title>
    <!-- Branch + Period filter -->
    <BranchFilterBar
      v-model="branchIds"
      :branches="branchStore.branches?.data || branchStore.branches || []"
      :period="period"
      :date-from="dateFrom"
      :date-to="dateTo"
      @period-change="onPeriodChange"
      @date-change="onDateChange"
    />

    <!-- Extra filters -->
    <v-card rounded="lg" border elevation="0" class="mb-5">
      <v-card-text class="pa-4">
        <v-row dense align="center">
          <v-col cols="12" sm="4">
            <v-text-field
              v-model="search"
              placeholder="Search product..."
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
              v-model="stockFilter"
              :items="stockFilterOptions"
              item-title="label"
              item-value="value"
              placeholder="Stock status"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
              @update:model-value="load"
            />
          </v-col>
          <v-col cols="6" sm="2">
            <v-btn-toggle
              v-model="viewMode"
              mandatory
              density="compact"
              variant="tonal"
              rounded="lg"
              color="primary"
            >
              <v-btn value="table" icon="mdi-table" size="small" />
              <v-btn value="cards" icon="mdi-view-grid" size="small" />
            </v-btn-toggle>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Summary cards -->
    <v-row dense class="mb-5">
      <!-- Stock health -->
      <v-col cols="6" sm="3">
        <v-card rounded="xl" border elevation="0" class="pa-4">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption text-medium-emphasis">
              Total Products
            </span>
            <v-avatar size="28" color="primary" variant="tonal" rounded="lg">
              <v-icon icon="mdi-package-variant" size="14" />
            </v-avatar>
          </div>
          <div class="text-h5 font-weight-black text-primary">
            {{ report.summary?.total_products ?? 0 }}
          </div>
          <div class="d-flex gap-2 mt-2">
            <v-chip size="x-small" color="success" variant="tonal" rounded="lg">
              {{ report.summary?.in_stock ?? 0 }} OK
            </v-chip>
            <v-chip size="x-small" color="warning" variant="tonal" rounded="lg">
              {{ report.summary?.low_stock ?? 0 }} Low
            </v-chip>
            <v-chip size="x-small" color="error" variant="tonal" rounded="lg">
              {{ report.summary?.out_of_stock ?? 0 }} Out
            </v-chip>
          </div>
        </v-card>
      </v-col>

      <!-- Stock cost value -->
      <v-col cols="6" sm="3">
        <v-card rounded="xl" border elevation="0" class="pa-4">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption text-medium-emphasis">
              Stock Cost Value
            </span>
            <v-avatar size="28" color="indigo" variant="tonal" rounded="lg">
              <v-icon icon="mdi-cash-multiple" size="14" />
            </v-avatar>
          </div>
          <div class="text-h5 font-weight-black text-indigo">
            {{ fmt(report.summary?.total_cost_value) }}
          </div>
          <div class="text-caption text-medium-emphasis mt-1">
            at cost price
          </div>
        </v-card>
      </v-col>

      <!-- Retail value -->
      <v-col cols="6" sm="3">
        <v-card rounded="xl" border elevation="0" class="pa-4">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption text-medium-emphasis">Retail Value</span>
            <v-avatar size="28" color="teal" variant="tonal" rounded="lg">
              <v-icon icon="mdi-tag-outline" size="14" />
            </v-avatar>
          </div>
          <div class="text-h5 font-weight-black text-teal">
            {{ fmt(report.summary?.total_retail_value) }}
          </div>
          <div class="text-caption text-medium-emphasis mt-1">
            at selling price
          </div>
        </v-card>
      </v-col>

      <!-- Potential profit -->
      <v-col cols="6" sm="3">
        <v-card rounded="xl" border elevation="0" class="pa-4">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption text-medium-emphasis">
              Potential Profit
            </span>
            <v-avatar size="28" color="success" variant="tonal" rounded="lg">
              <v-icon icon="mdi-trending-up" size="14" />
            </v-avatar>
          </div>
          <div class="text-h5 font-weight-black text-success">
            {{ fmt(report.summary?.potential_profit) }}
          </div>
          <div class="text-caption text-medium-emphasis mt-1">
            if all stock sold
          </div>
        </v-card>
      </v-col>
    </v-row>

    <v-row dense>
      <!-- By category chart -->
      <v-col cols="12" md="4">
        <v-card rounded="xl" border elevation="0" class="mb-4 fill-height">
          <div class="pa-5 pb-3 text-body-1 font-weight-bold">By Category</div>
          <v-divider />
          <v-card-text class="pa-4">
            <div
              v-for="cat in report.by_category"
              :key="cat.category"
              class="mb-3"
            >
              <div class="d-flex justify-space-between align-center mb-1">
                <span class="text-body-2 font-weight-medium">
                  {{ cat.category }}
                </span>
                <div class="d-flex align-center gap-2">
                  <span class="text-caption font-weight-bold">
                    {{ fmt(cat.stock_value) }}
                  </span>
                  <span class="text-caption text-medium-emphasis">
                    {{ cat.count }} products
                  </span>
                </div>
              </div>
              <v-progress-linear
                :model-value="(cat.stock_value / maxCategoryValue) * 100"
                color="primary"
                height="5"
                rounded
                bg-color="grey-lighten-3"
              />
              <div
                v-if="cat.out_of_stock > 0 || cat.low_stock > 0"
                class="d-flex gap-1 mt-1"
              >
                <v-chip
                  v-if="cat.out_of_stock"
                  size="x-small"
                  color="error"
                  variant="tonal"
                  rounded="lg"
                >
                  {{ cat.out_of_stock }} out
                </v-chip>
                <v-chip
                  v-if="cat.low_stock"
                  size="x-small"
                  color="warning"
                  variant="tonal"
                  rounded="lg"
                >
                  {{ cat.low_stock }} low
                </v-chip>
              </div>
            </div>
            <div
              v-if="!report.by_category?.length"
              class="text-caption text-medium-emphasis text-center py-6"
            >
              No data
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Movement summary (if date range) -->
      <v-col cols="12" md="8">
        <v-card rounded="xl" border elevation="0" class="mb-4">
          <div class="d-flex align-center justify-space-between pa-5 pb-3">
            <div class="text-body-1 font-weight-bold">Movement Summary</div>
            <v-chip
              v-if="dateFrom && dateTo"
              size="small"
              color="primary"
              variant="tonal"
              rounded="lg"
            >
              {{ fmtDate(dateFrom) }} — {{ fmtDate(dateTo) }}
            </v-chip>
            <v-chip
              v-else
              size="small"
              color="grey"
              variant="tonal"
              rounded="lg"
            >
              Select date range to see movements
            </v-chip>
          </div>
          <v-divider />
          <v-card-text class="pa-4">
            <v-row dense v-if="report.movement_summary?.length">
              <v-col
                v-for="m in report.movement_summary"
                :key="m.movement_type"
                cols="6"
                sm="4"
              >
                <v-card rounded="lg" border elevation="0" class="pa-3">
                  <div class="d-flex align-center gap-2 mb-1">
                    <v-icon
                      :icon="movementIcon(m.movement_type)"
                      :color="movementColor(m.movement_type)"
                      size="16"
                    />
                    <span class="text-caption font-weight-bold">
                      {{ movementLabel(m.movement_type) }}
                    </span>
                  </div>
                  <div class="text-body-1 font-weight-black">
                    {{ m.total_qty }}
                    <span
                      class="text-caption text-medium-emphasis font-weight-regular"
                    >
                      units
                    </span>
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ m.count }} transactions
                  </div>
                </v-card>
              </v-col>
            </v-row>
            <div v-else class="text-center py-8">
              <v-icon
                icon="mdi-calendar-range"
                size="40"
                color="grey-lighten-2"
                class="mb-2"
              />
              <div class="text-caption text-medium-emphasis">
                Select a date range above to see stock movements
              </div>
            </div>
          </v-card-text>
        </v-card>

        <!-- Top movers (if date range) -->
        <v-card
          v-if="report.product_movements?.length"
          rounded="xl"
          border
          elevation="0"
        >
          <div class="pa-5 pb-3 text-body-1 font-weight-bold">
            Top Stock Movers
          </div>
          <v-divider />
          <v-data-table
            :headers="movementHeaders"
            :items="report.product_movements ?? []"
            :items-per-page="10"
            density="compact"
            item-value="product_id"
          >
            <template #item.product_name="{ item }">
              <div class="d-flex align-center gap-2 py-1">
                <v-avatar
                  size="30"
                  rounded="lg"
                  class="bg-grey-lighten-4 border flex-shrink-0"
                >
                  <v-img v-if="item.image_url" :src="item.image_url" cover />
                  <v-icon
                    v-else
                    icon="mdi-package-variant"
                    size="14"
                    color="grey"
                  />
                </v-avatar>
                <span class="text-body-2 font-weight-medium">
                  {{ item.product_name }}
                </span>
              </div>
            </template>
            <template #item.total_in="{ item }">
              <span class="text-success font-weight-bold">
                +{{ item.total_in }}
              </span>
            </template>
            <template #item.total_out="{ item }">
              <span class="text-error font-weight-bold">
                -{{ item.total_out }}
              </span>
            </template>
            <template #item.net="{ item }">
              <span
                :class="
                  item.total_in - item.total_out >= 0
                    ? 'text-success'
                    : 'text-error'
                "
                class="font-weight-bold"
              >
                {{ item.total_in - item.total_out >= 0 ? '+' : ''
                }}{{ item.total_in - item.total_out }}
              </span>
            </template>
          </v-data-table>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Product stock table / cards ──────────────────────────────────── -->
    <v-card rounded="xl" border elevation="0" class="mt-4">
      <div class="d-flex align-center justify-space-between pa-5 pb-3">
        <div class="text-body-1 font-weight-bold">
          All Products
          <v-chip
            size="x-small"
            color="primary"
            variant="tonal"
            rounded="lg"
            class="ml-2"
          >
            {{ filteredProducts.length }}
          </v-chip>
        </div>
      </div>
      <v-divider />

      <!-- TABLE VIEW -->
      <v-data-table
        v-if="viewMode === 'table'"
        :headers="productHeaders"
        :items="filteredProducts"
        :items-per-page="10"
        item-value="id"
      >
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <v-avatar
              size="36"
              rounded="lg"
              class="bg-grey-lighten-4 border flex-shrink-0"
            >
              <v-img v-if="item.image_url" :src="item.image_url" cover />
              <v-icon
                v-else
                icon="mdi-package-variant"
                size="16"
                color="grey"
              />
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-bold">{{ item.name }}</div>
              <div class="text-caption text-medium-emphasis">
                {{ item.sku ?? '—' }}
              </div>
            </div>
          </div>
        </template>

        <template #item.category="{ item }">
          <span class="text-caption">{{ item.category ?? '—' }}</span>
        </template>

        <template #item.stock_quantity="{ item }">
          <div>
            <span
              class="font-weight-black"
              :class="stockClass(item.stock_status)"
            >
              {{ item.stock_quantity }}
            </span>
            <span class="text-caption text-medium-emphasis ml-1">
              {{ item.unit ?? 'pcs' }}
            </span>
          </div>
        </template>

        <template #item.reorder_level="{ item }">
          <span class="text-body-2">{{ item.reorder_level ?? '—' }}</span>
        </template>

        <template #item.stock_value="{ item }">
          <span class="font-weight-bold">{{ fmt(item.stock_value) }}</span>
        </template>

        <template #item.retail_value="{ item }">
          <span class="text-success font-weight-bold">
            {{ fmt(item.retail_value) }}
          </span>
        </template>

        <template #item.stock_status="{ item }">
          <v-chip
            size="x-small"
            rounded="lg"
            variant="tonal"
            :color="statusColor(item.stock_status)"
            :prepend-icon="statusIcon(item.stock_status)"
          >
            {{ statusLabel(item.stock_status) }}
          </v-chip>
        </template>

        <template #no-data>
          <div class="text-center py-10">
            <v-icon
              icon="mdi-package-variant-remove"
              size="48"
              color="grey-lighten-2"
              class="mb-2"
            />
            <div class="text-body-2 text-grey">No products found</div>
          </div>
        </template>
      </v-data-table>

      <!-- CARD VIEW -->
      <v-card-text v-else class="pa-4">
        <v-row dense>
          <v-col
            v-for="p in filteredProducts"
            :key="p.id"
            cols="6"
            sm="4"
            md="3"
            lg="2"
          >
            <v-card
              rounded="xl"
              border
              elevation="0"
              :class="['product-stock-card', `card-${p.stock_status}`]"
            >
              <div class="product-thumb">
                <v-img
                  v-if="p.image_url"
                  :src="p.image_url"
                  cover
                  height="80"
                />
                <div v-else class="thumb-placeholder">
                  {{ p.name?.charAt(0)?.toUpperCase() }}
                </div>
                <v-chip
                  size="x-small"
                  rounded="lg"
                  variant="flat"
                  :color="statusColor(p.stock_status)"
                  class="status-chip"
                >
                  {{ statusLabel(p.stock_status) }}
                </v-chip>
              </div>
              <div class="pa-2">
                <div class="text-caption font-weight-bold text-truncate mb-1">
                  {{ p.name }}
                </div>
                <div
                  class="text-h6 font-weight-black"
                  :class="stockClass(p.stock_status)"
                >
                  {{ p.stock_quantity }}
                  <span
                    class="text-caption text-medium-emphasis font-weight-regular"
                  >
                    {{ p.unit ?? 'pcs' }}
                  </span>
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ fmt(p.stock_value) }} value
                </div>
              </div>
            </v-card>
          </v-col>
        </v-row>
        <div v-if="!filteredProducts.length" class="text-center py-10">
          <v-icon
            icon="mdi-package-variant-remove"
            size="48"
            color="grey-lighten-2"
            class="mb-2"
          />
          <div class="text-body-2 text-grey">No products found</div>
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useAuthStore } from '@/stores/authStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import api from '@/api/api'
  import BranchFilterBar from '@/components/common/BranchFilterBar.vue'

  const authStore = useAuthStore()
  const branchStore = useBranchStore()
  const { notif } = useAppUtils()

  const loading = ref(false)
  const period = ref('month')
  const dateFrom = ref('')
  const dateTo = ref('')
  const branchIds = ref([])
  const search = ref('')
  const stockFilter = ref(null)
  const viewMode = ref('table')

  const report = ref({
    summary: {},
    products: [],
    by_category: [],
    movement_summary: [],
    product_movements: []
  })

  const stockFilterOptions = [
    { value: 'in_stock', label: 'In Stock' },
    { value: 'low_stock', label: 'Low Stock' },
    { value: 'out_of_stock', label: 'Out of Stock' }
  ]

  // ── Filtered products ─────────────────────────────────────────────────────
  const filteredProducts = computed(() => {
    let list = report.value.products ?? []
    if (stockFilter.value)
      list = list.filter(p => p.stock_status === stockFilter.value)
    if (search.value) {
      const q = search.value.toLowerCase()
      list = list.filter(
        p =>
          p.name.toLowerCase().includes(q) ||
          (p.sku ?? '').toLowerCase().includes(q)
      )
    }
    return list
  })

  const maxCategoryValue = computed(() =>
    Math.max(...(report.value.by_category ?? []).map(c => c.stock_value), 1)
  )

  // ── Table headers ─────────────────────────────────────────────────────────
  const productHeaders = [
    { title: 'Product', key: 'name', sortable: false },
    { title: 'Category', key: 'category', sortable: true },
    { title: 'Stock', key: 'stock_quantity', sortable: true },
    { title: 'Reorder At', key: 'reorder_level', sortable: true },
    { title: 'Cost Value', key: 'stock_value', sortable: true },
    { title: 'Retail Value', key: 'retail_value', sortable: true },
    { title: 'Status', key: 'stock_status', sortable: false }
  ]

  const movementHeaders = [
    { title: 'Product', key: 'product_name', sortable: false },
    { title: 'In (+)', key: 'total_in', sortable: true },
    { title: 'Out (-)', key: 'total_out', sortable: true },
    { title: 'Net', key: 'net', sortable: false }
  ]

  // ── Helpers ───────────────────────────────────────────────────────────────
  const statusColor = s =>
    ({ in_stock: 'success', low_stock: 'warning', out_of_stock: 'error' })[s] ??
    'grey'
  const statusIcon = s =>
    ({
      in_stock: 'mdi-check-circle',
      low_stock: 'mdi-alert',
      out_of_stock: 'mdi-close-circle'
    })[s] ?? 'mdi-circle'
  const statusLabel = s =>
    ({
      in_stock: 'In Stock',
      low_stock: 'Low Stock',
      out_of_stock: 'Out of Stock'
    })[s] ?? s
  const stockClass = s =>
    ({
      in_stock: 'text-success',
      low_stock: 'text-warning',
      out_of_stock: 'text-error'
    })[s] ?? ''

  const movementIcon = t =>
    ({
      purchase: 'mdi-package-down',
      sale: 'mdi-cart-outline',
      adjustment_in: 'mdi-plus-circle-outline',
      adjustment_out: 'mdi-minus-circle-outline',
      waste: 'mdi-trash-can-outline',
      count: 'mdi-clipboard-check-outline'
    })[t] ?? 'mdi-circle'
  const movementColor = t =>
    ({
      purchase: 'success',
      sale: 'primary',
      adjustment_in: 'teal',
      adjustment_out: 'orange',
      waste: 'error',
      count: 'purple'
    })[t] ?? 'grey'
  const movementLabel = t =>
    ({
      purchase: 'Purchase',
      sale: 'Sale',
      adjustment_in: 'Adj In',
      adjustment_out: 'Adj Out',
      waste: 'Waste',
      count: 'Count'
    })[t] ?? t

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
      : ''

  // ── Period & dates ────────────────────────────────────────────────────────
  const periodDates = p => {
    const now = new Date()
    const pad = n => String(n).padStart(2, '0')
    const ymd = d =>
      `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
    if (p === 'today') {
      const t = ymd(now)
      return { from: t, to: t }
    }
    if (p === 'week') {
      const m = new Date(now)
      m.setDate(now.getDate() - now.getDay() + 1)
      return { from: ymd(m), to: ymd(now) }
    }
    if (p === 'month') {
      return {
        from: `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`,
        to: ymd(now)
      }
    }
    if (p === 'year') {
      return { from: `${now.getFullYear()}-01-01`, to: ymd(now) }
    }
    return { from: '', to: '' }
  }

  const onPeriodChange = p => {
    period.value = p
    if (p !== 'custom') {
      const d = periodDates(p)
      dateFrom.value = d.from
      dateTo.value = d.to
    }
    load()
  }

  const onDateChange = ({ from, to }) => {
    dateFrom.value = from
    dateTo.value = to
    period.value = 'custom'
    if (from && to) load()
  }

  let searchTimer = null
  const onSearch = () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(load, 400)
  }

  // ── Load ──────────────────────────────────────────────────────────────────
  const load = async () => {
    loading.value = true
    try {
      const res = await api.get('v1/mart/reports/inventory', {
        params: {
          branch_id: branchIds.value[0] ?? authStore.branch_id,
          date_from: dateFrom.value || undefined,
          date_to: dateTo.value || undefined,
          search: search.value || undefined
        }
      })
      report.value = res.data.data
    } catch (e) {
      notif('Failed to load report', { type: 'error' })
    } finally {
      loading.value = false
    }
  }

  onMounted(async () => {
    await branchStore.fetchBranches()
    load()
  })
</script>

<style scoped>
  .gap-2 {
    gap: 8px;
  }

  /* Product stock cards */
  .product-stock-card {
    overflow: hidden;
    transition: all 0.15s;
  }
  .product-stock-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08) !important;
  }
  .card-out_of_stock {
    border-color: rgba(var(--v-theme-error), 0.35) !important;
  }
  .card-low_stock {
    border-color: rgba(var(--v-theme-warning), 0.45) !important;
  }

  .product-thumb {
    position: relative;
    background: #f8fafc;
  }
  .thumb-placeholder {
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 900;
    color: #c0ccd8;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
  }
  .status-chip {
    position: absolute;
    top: 5px;
    right: 5px;
  }
</style>
