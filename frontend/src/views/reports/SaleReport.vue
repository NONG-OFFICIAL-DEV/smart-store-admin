<template>
  <custom-title
    icon="mdi-sale"
    title="Sales Summary"
    subtitle="Daily revenue · Orders · Profit breakdown"
  ></custom-title>
  <div class="sales-page">
    <!-- ── Page Header ─────────────────────────────────────────────────────── -->
    <div class="page-header mb-6">
      <div class="d-flex align-center justify-space-between flex-wrap gap-3">
        <div class="d-flex gap-2 align-center flex-wrap">
          <v-select
            v-model="filters.branch_id"
            :items="branches.data"
            item-value="id"
            item-title="name"
            label="Branch"
            variant="outlined"
            density="compact"
            rounded="lg"
            hide-details
            min-width="180"
            prepend-inner-icon="mdi-store-outline"
            clearable
          />
          <v-text-field
            v-model="filters.date_from"
            type="date"
            label="From"
            variant="outlined"
            density="compact"
            rounded="lg"
            hide-details
            min-width="160"
          />
          <v-text-field
            v-model="filters.date_to"
            type="date"
            label="To"
            variant="outlined"
            density="compact"
            rounded="lg"
            hide-details
            min-width="160"
          />
          <v-btn
            color="primary"
            variant="flat"
            rounded="lg"
            prepend-icon="mdi-magnify"
            :loading="loading"
            @click="fetchData"
          >
            Search
          </v-btn>
          <v-btn
            variant="tonal"
            rounded="lg"
            prepend-icon="mdi-download-outline"
            @click="exportCsv"
          >
            Export
          </v-btn>
        </div>
      </div>
    </div>

    <!-- ── Period Totals (aggregate of filtered range) ──────────────────────── -->
    <v-row class="mb-5" dense>
      <v-col v-for="(kpi, i) in kpis" :key="i" cols="6" sm="4" md="2">
        <v-card class="kpi-card" rounded="xl" border elevation="0">
          <v-card-text class="pa-4">
            <div class="d-flex align-center justify-space-between mb-2">
              <v-avatar
                :color="kpi.color"
                variant="tonal"
                rounded="lg"
                size="36"
              >
                <v-icon :icon="kpi.icon" size="18" />
              </v-avatar>
              <v-chip
                v-if="kpi.trend !== null"
                :color="kpi.trend >= 0 ? 'success' : 'error'"
                size="x-small"
                variant="tonal"
              >
                <v-icon
                  :icon="
                    kpi.trend >= 0 ? 'mdi-trending-up' : 'mdi-trending-down'
                  "
                  size="12"
                  class="mr-1"
                />
                {{ Math.abs(kpi.trend) }}%
              </v-chip>
            </div>
            <div class="kpi-value">{{ kpi.value }}</div>
            <div class="kpi-label">{{ kpi.label }}</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Charts Row ──────────────────────────────────────────────────────── -->
    <v-row class="mb-5" dense>
      <!-- Revenue chart -->
      <v-col cols="12" md="8">
        <v-card rounded="xl" border elevation="0" height="320">
          <v-card-text class="pa-4 h-100 d-flex flex-column">
            <div class="d-flex align-center justify-space-between mb-4">
              <div class="chart-title">Revenue Trend</div>
              <v-btn-toggle
                v-model="chartMetric"
                color="primary"
                variant="tonal"
                rounded="lg"
                mandatory
                density="compact"
              >
                <v-btn value="total_revenue" size="small" class="text-none">
                  Revenue
                </v-btn>
                <v-btn value="net_revenue" size="small" class="text-none">
                  Net
                </v-btn>
                <v-btn value="gross_profit" size="small" class="text-none">
                  Profit
                </v-btn>
              </v-btn-toggle>
            </div>
            <div class="chart-area flex-grow-1" ref="revenueChartRef">
              <svg
                v-if="chartData.length"
                class="sparkline"
                :viewBox="`0 0 ${svgW} ${svgH}`"
                preserveAspectRatio="none"
              >
                <defs>
                  <linearGradient id="revGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#6366f1" stop-opacity="0.3" />
                    <stop offset="100%" stop-color="#6366f1" stop-opacity="0" />
                  </linearGradient>
                </defs>
                <path :d="areaPath" fill="url(#revGrad)" />
                <path
                  :d="linePath"
                  fill="none"
                  stroke="#6366f1"
                  stroke-width="2.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />

                <!-- Data points -->
                <circle
                  v-for="(pt, i) in chartPoints"
                  :key="i"
                  :cx="pt.x"
                  :cy="pt.y"
                  r="3.5"
                  fill="#6366f1"
                  stroke="white"
                  stroke-width="2"
                  class="chart-dot"
                  @mouseenter="hoveredPoint = { ...pt, index: i }"
                  @mouseleave="hoveredPoint = null"
                />

                <!-- Tooltip -->
                <g v-if="hoveredPoint">
                  <rect
                    :x="hoveredPoint.x - 50"
                    :y="hoveredPoint.y - 36"
                    width="100"
                    height="28"
                    rx="6"
                    fill="#1e1b4b"
                    opacity="0.9"
                  />
                  <text
                    :x="hoveredPoint.x"
                    :y="hoveredPoint.y - 17"
                    text-anchor="middle"
                    fill="white"
                    font-size="11"
                    font-weight="600"
                  >
                    {{
                      formatCurrency(
                        chartData[hoveredPoint.index]?.[chartMetric]
                      )
                    }}
                  </text>
                  <text
                    :x="hoveredPoint.x"
                    :y="hoveredPoint.y - 6"
                    text-anchor="middle"
                    fill="#a5b4fc"
                    font-size="9"
                  >
                    {{ chartData[hoveredPoint.index]?.date }}
                  </text>
                </g>
              </svg>
              <div v-else class="chart-empty">
                <v-icon
                  icon="mdi-chart-line"
                  size="40"
                  color="grey-lighten-2"
                />
                <p class="text-caption text-grey mt-2">
                  No data for selected period
                </p>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Order type breakdown -->
      <v-col cols="12" md="4">
        <v-card rounded="xl" border elevation="0" height="320">
          <v-card-text class="pa-4 h-100 d-flex flex-column">
            <div class="chart-title mb-4">Order Types</div>
            <div class="d-flex flex-column gap-3 flex-grow-1 justify-center">
              <div
                v-for="type in orderTypes"
                :key="type.key"
                class="order-type-row"
              >
                <div class="d-flex align-center justify-space-between mb-1">
                  <div class="d-flex align-center gap-2">
                    <v-avatar
                      :color="type.color"
                      variant="tonal"
                      size="28"
                      rounded="lg"
                    >
                      <v-icon :icon="type.icon" size="14" />
                    </v-avatar>
                    <span class="text-body-2 font-weight-medium">
                      {{ type.label }}
                    </span>
                  </div>
                  <span class="text-body-2 font-weight-bold">
                    {{ totals[type.key] || 0 }}
                  </span>
                </div>
                <v-progress-linear
                  :model-value="orderTypePercent(type.key)"
                  :color="type.color"
                  rounded
                  height="6"
                  bg-color="grey-lighten-3"
                />
                <div class="text-caption text-grey text-right">
                  {{ orderTypePercent(type.key) }}%
                </div>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Data Table ─────────────────────────────────────────────────────── -->
    <v-card rounded="xl" border elevation="0">
      <v-card-title class="pa-5 pb-0">
        <div class="d-flex align-center justify-space-between">
          <span class="text-h6 font-weight-bold">Daily Breakdown</span>
          <v-text-field
            v-model="search"
            prepend-inner-icon="mdi-magnify"
            placeholder="Search by date..."
            variant="outlined"
            density="compact"
            rounded="lg"
            hide-details
            max-width="220"
          />
        </div>
      </v-card-title>

      <v-data-table
        :headers="headers"
        :items="filteredSummaries"
        :loading="loading"
        :search="search"
        item-value="id"
        hover
        rounded="xl"
        :items-per-page="15"
      >
        <!-- Date -->
        <template #item.date="{ item }">
          <div class="d-flex align-center gap-2">
            <v-avatar color="primary" variant="tonal" size="32" rounded="lg">
              <span class="text-caption font-weight-bold">
                {{ dayOfMonth(item.date) }}
              </span>
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-medium">
                {{ formatDate(item.date) }}
              </div>
              <div class="text-caption text-grey">{{ dayName(item.date) }}</div>
            </div>
          </div>
        </template>

        <!-- Branch -->
        <template #item.branch="{ item }">
          <v-chip
            size="small"
            variant="tonal"
            color="primary"
            prepend-icon="mdi-store-outline"
          >
            {{ item.branch?.name || '—' }}
          </v-chip>
        </template>

        <!-- Orders -->
        <template #item.total_orders="{ item }">
          <div class="d-flex align-center gap-1">
            <v-icon icon="mdi-receipt-outline" size="14" color="grey" />
            <span class="text-body-2 font-weight-medium">
              {{ item.total_orders }}
            </span>
          </div>
        </template>

        <!-- Revenue -->
        <template #item.total_revenue="{ item }">
          <span class="text-body-2 font-weight-bold text-primary">
            {{ formatCurrency(item.total_revenue) }}
          </span>
        </template>

        <!-- Discount -->
        <template #item.total_discount="{ item }">
          <span class="text-body-2 text-error">
            {{
              item.total_discount > 0
                ? '-' + formatCurrency(item.total_discount)
                : '—'
            }}
          </span>
        </template>

        <!-- Tax -->
        <template #item.total_tax="{ item }">
          <span class="text-body-2 text-medium-emphasis">
            {{ formatCurrency(item.total_tax) }}
          </span>
        </template>

        <!-- Net Revenue -->
        <template #item.net_revenue="{ item }">
          <span class="text-body-2 font-weight-bold text-success">
            {{ formatCurrency(item.net_revenue) }}
          </span>
        </template>

        <!-- Gross Profit -->
        <template #item.gross_profit="{ item }">
          <div
            v-if="item.gross_profit != null"
            class="d-flex align-center gap-1"
          >
            <span class="text-body-2 font-weight-medium">
              {{ formatCurrency(item.gross_profit) }}
            </span>
            <v-chip
              :color="
                profitMargin(item) >= 30
                  ? 'success'
                  : profitMargin(item) >= 15
                    ? 'warning'
                    : 'error'
              "
              size="x-small"
              variant="tonal"
            >
              {{ profitMargin(item) }}%
            </v-chip>
          </div>
          <span v-else class="text-grey">—</span>
        </template>

        <!-- Avg Order -->
        <template #item.avg_order_value="{ item }">
          <span class="text-body-2">
            {{
              item.avg_order_value ? formatCurrency(item.avg_order_value) : '—'
            }}
          </span>
        </template>

        <!-- Order types mini -->
        <template #item.order_types="{ item }">
          <div class="d-flex gap-2">
            <v-chip
              v-if="item.dine_in_orders"
              size="x-small"
              color="indigo"
              variant="tonal"
              prepend-icon="mdi-silverware-fork-knife"
            >
              {{ item.dine_in_orders }}
            </v-chip>
            <v-chip
              v-if="item.takeaway_orders"
              size="x-small"
              color="orange"
              variant="tonal"
              prepend-icon="mdi-bag-personal-outline"
            >
              {{ item.takeaway_orders }}
            </v-chip>
            <v-chip
              v-if="item.delivery_orders"
              size="x-small"
              color="teal"
              variant="tonal"
              prepend-icon="mdi-moped-outline"
            >
              {{ item.delivery_orders }}
            </v-chip>
          </div>
        </template>

        <!-- New customers -->
        <template #item.new_customers="{ item }">
          <div v-if="item.new_customers" class="d-flex align-center gap-1">
            <v-icon icon="mdi-account-plus-outline" size="14" color="success" />
            <span class="text-body-2 text-success font-weight-medium">
              {{ item.new_customers }}
            </span>
          </div>
          <span v-else class="text-grey">—</span>
        </template>

        <!-- No data -->
        <template #no-data>
          <div class="text-center py-12">
            <v-icon
              icon="mdi-chart-bar-stacked"
              size="56"
              color="grey-lighten-1"
              class="mb-3"
            />
            <p class="text-h6 text-medium-emphasis mb-1">No sales data</p>
            <p class="text-body-2 text-grey">
              Try adjusting the date range or branch filter
            </p>
          </div>
        </template>

        <!-- Loading -->
        <template #loading>
          <v-skeleton-loader v-for="n in 8" :key="n" type="table-row" />
        </template>
      </v-data-table>
    </v-card>
  </div>
</template>

<script setup>
  import { ref, computed, onMounted, watch } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useReportStore } from '@/stores/reportStore'
  import { useBranchStore } from '@/stores/branchStore'

  const salesStore = useReportStore()
  const branchStore = useBranchStore()

  const { summaries, loading } = storeToRefs(salesStore)
  const { branches } = storeToRefs(branchStore)

  // ── Filters ───────────────────────────────────────────────────────────────────
  const today = new Date().toISOString().split('T')[0]
  const lastMonth = new Date(Date.now() - 30 * 864e5)
    .toISOString()
    .split('T')[0]

  const filters = ref({
    branch_id: null,
    date_from: lastMonth,
    date_to: today
  })

  const search = ref('')
  const chartMetric = ref('total_revenue')
  const hoveredPoint = ref(null)

  // ── SVG chart dimensions ──────────────────────────────────────────────────────
  const svgW = 600
  const svgH = 160
  const padX = 20
  const padY = 16

  // ── Computed: totals across filtered data ─────────────────────────────────────
  const totals = computed(() => {
    const list = summaries.value
    return {
      total_orders: list.reduce((s, r) => s + (r.total_orders || 0), 0),
      total_revenue: list.reduce((s, r) => s + Number(r.total_revenue || 0), 0),
      total_discount: list.reduce(
        (s, r) => s + Number(r.total_discount || 0),
        0
      ),
      total_tax: list.reduce((s, r) => s + Number(r.total_tax || 0), 0),
      net_revenue: list.reduce((s, r) => s + Number(r.net_revenue || 0), 0),
      gross_profit: list.reduce((s, r) => s + Number(r.gross_profit || 0), 0),
      dine_in_orders: list.reduce((s, r) => s + (r.dine_in_orders || 0), 0),
      takeaway_orders: list.reduce((s, r) => s + (r.takeaway_orders || 0), 0),
      delivery_orders: list.reduce((s, r) => s + (r.delivery_orders || 0), 0),
      new_customers: list.reduce((s, r) => s + (r.new_customers || 0), 0)
    }
  })

  const kpis = computed(() => [
    {
      label: 'Total Orders',
      value: totals.value.total_orders,
      icon: 'mdi-receipt-outline',
      color: 'primary',
      trend: null
    },
    {
      label: 'Gross Revenue',
      value: formatCurrency(totals.value.total_revenue),
      icon: 'mdi-cash-multiple',
      color: 'indigo',
      trend: null
    },
    {
      label: 'Discounts',
      value: formatCurrency(totals.value.total_discount),
      icon: 'mdi-tag-minus-outline',
      color: 'error',
      trend: null
    },
    {
      label: 'Tax Collected',
      value: formatCurrency(totals.value.total_tax),
      icon: 'mdi-percent-outline',
      color: 'orange',
      trend: null
    },
    {
      label: 'Net Revenue',
      value: formatCurrency(totals.value.net_revenue),
      icon: 'mdi-trending-up',
      color: 'success',
      trend: null
    },
    {
      label: 'Gross Profit',
      value: formatCurrency(totals.value.gross_profit),
      icon: 'mdi-chart-bar',
      color: 'teal',
      trend: null
    }
  ])

  const orderTypes = [
    {
      key: 'dine_in_orders',
      label: 'Dine In',
      icon: 'mdi-silverware-fork-knife',
      color: 'indigo'
    },
    {
      key: 'takeaway_orders',
      label: 'Takeaway',
      icon: 'mdi-bag-personal-outline',
      color: 'orange'
    },
    {
      key: 'delivery_orders',
      label: 'Delivery',
      icon: 'mdi-moped-outline',
      color: 'teal'
    }
  ]

  const orderTypePercent = key => {
    const total = totals.value.total_orders
    if (!total) return 0
    return Math.round((totals.value[key] / total) * 100)
  }

  // ── SVG chart ─────────────────────────────────────────────────────────────────
  const chartData = computed(() =>
    [...summaries.value].sort((a, b) => a.date.localeCompare(b.date))
  )

  const chartPoints = computed(() => {
    const data = chartData.value
    if (!data.length) return []

    const values = data.map(d => Number(d[chartMetric.value] || 0))
    const minV = Math.min(...values)
    const maxV = Math.max(...values)
    const range = maxV - minV || 1

    return data.map((d, i) => ({
      x: padX + (i / Math.max(data.length - 1, 1)) * (svgW - padX * 2),
      y:
        padY +
        (1 - (Number(d[chartMetric.value] || 0) - minV) / range) *
          (svgH - padY * 2)
    }))
  })

  const linePath = computed(() => {
    const pts = chartPoints.value
    if (!pts.length) return ''
    return pts.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ')
  })

  const areaPath = computed(() => {
    const pts = chartPoints.value
    if (!pts.length) return ''
    const line = pts
      .map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`)
      .join(' ')
    return `${line} L ${pts[pts.length - 1].x} ${svgH} L ${pts[0].x} ${svgH} Z`
  })

  // ── Table ─────────────────────────────────────────────────────────────────────
  const headers = [
    { title: 'Date', key: 'date', sortable: true },
    { title: 'Branch', key: 'branch', sortable: false },
    { title: 'Orders', key: 'total_orders', sortable: true },
    { title: 'Revenue', key: 'total_revenue', sortable: true },
    { title: 'Discount', key: 'total_discount', sortable: false },
    { title: 'Tax', key: 'total_tax', sortable: false },
    { title: 'Net Revenue', key: 'net_revenue', sortable: true },
    { title: 'Profit', key: 'gross_profit', sortable: true },
    { title: 'Avg Order', key: 'avg_order_value', sortable: true },
    { title: 'Types', key: 'order_types', sortable: false },
    { title: 'New Guests', key: 'new_customers', sortable: true }
  ]

  const filteredSummaries = computed(() => summaries.value)

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const formatCurrency = val =>
    new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(val || 0)

  const formatDate = d =>
    new Date(d).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    })
  const dayName = d =>
    new Date(d).toLocaleDateString('en-US', { weekday: 'long' })
  const dayOfMonth = d => new Date(d).getDate()
  const profitMargin = item => {
    if (!item.gross_profit || !item.total_revenue) return 0
    return Math.round((item.gross_profit / item.total_revenue) * 100)
  }

  // ── Actions ───────────────────────────────────────────────────────────────────
  const fetchData = async () => {
    // salesStore.fetchSummaries sets store.summaries array
    await salesStore.fetchSummaries(filters.value)
  }

  const exportCsv = () => {
    const rows = [
      [
        'Date',
        'Branch',
        'Orders',
        'Revenue',
        'Discount',
        'Tax',
        'Net Revenue',
        'Gross Profit',
        'Avg Order',
        'Dine In',
        'Takeaway',
        'Delivery',
        'New Customers'
      ],
      ...summaries.value.map(r => [
        r.date,
        r.branch?.name,
        r.total_orders,
        r.total_revenue,
        r.total_discount,
        r.total_tax,
        r.net_revenue,
        r.gross_profit ?? '',
        r.avg_order_value ?? '',
        r.dine_in_orders,
        r.takeaway_orders,
        r.delivery_orders,
        r.new_customers
      ])
    ]
    const csv = rows.map(r => r.join(',')).join('\n')
    const blob = new Blob([csv], { type: 'text/csv' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `sales-summary-${filters.value.date_from}-to-${filters.value.date_to}.csv`
    a.click()
    URL.revokeObjectURL(url)
  }

  onMounted(async () => {
    await Promise.all([branchStore.fetchBranches?.(), fetchData()])
  })
</script>

<style scoped>
  .sales-page {
    padding: 0;
  }

  .page-title {
    font-size: 1.6rem;
    font-weight: 800;
    letter-spacing: -0.5px;
    color: rgb(var(--v-theme-on-surface));
  }
  .page-subtitle {
    font-size: 0.85rem;
    color: rgb(var(--v-theme-on-surface-variant));
    margin-top: 2px;
  }

  /* ── KPI cards ──────────────────────────────────────────────────────────────── */
  .kpi-card {
    transition: transform 0.2s ease;
  }
  .kpi-card:hover {
    transform: translateY(-2px);
  }

  .kpi-value {
    font-size: 1.25rem;
    font-weight: 800;
    line-height: 1.2;
    letter-spacing: -0.3px;
    margin-bottom: 2px;
  }
  .kpi-label {
    font-size: 0.72rem;
    color: rgb(var(--v-theme-on-surface-variant));
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  /* ── Chart ──────────────────────────────────────────────────────────────────── */
  .chart-title {
    font-size: 0.95rem;
    font-weight: 700;
    letter-spacing: -0.2px;
  }
  .chart-area {
    position: relative;
    min-height: 140px;
  }
  .sparkline {
    width: 100%;
    height: 100%;
    overflow: visible;
  }
  .chart-dot {
    cursor: pointer;
    transition: r 0.15s;
  }
  .chart-dot:hover {
    r: 5;
  }
  .chart-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    min-height: 140px;
  }

  /* ── Order type rows ─────────────────────────────────────────────────────────── */
  .order-type-row {
    margin-bottom: 4px;
  }

  /* ── Gaps ───────────────────────────────────────────────────────────────────── */
  .gap-2 {
    gap: 8px;
  }
  .gap-3 {
    gap: 12px;
  }
  .h-100 {
    height: 100%;
  }
</style>
