<template>
  <div class="report-page">
    <custom-title
      icon="mdi-note"
      title="Order Report"
      subtitle="Sales analytics &amp; order history"
    >
      <template #right>
        <v-btn
          color="success"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-microsoft-excel"
          :loading="exporting"
          :disabled="!stats"
          @click="exportExcel"
        >
          Export Excel
        </v-btn>
      </template>
    </custom-title>


    <BranchFilterBar
      v-model="filters.branch_ids"
      :branches="branchStore.branches?.data || branchStore.branches || []"
      :period="period"
      @period-change="onPeriodChange"
      @date-change="
        ({ from, to }) => {
          filters.date_from = from
          filters.date_to = to
          loadAll()
        }
      "
    />

    <!-- ══ KPI Cards ════════════════════════════════════════════════════════ -->
    <v-row dense class="mb-5">
      <v-col v-for="kpi in kpiCards" :key="kpi.label" cols="6" sm="3">
        <v-card rounded="xl" border elevation="0" class="kpi-card pa-4">
          <div class="d-flex align-center justify-space-between mb-3">
            <div class="kpi-icon-wrap" :style="`background:${kpi.bg}`">
              <v-icon :icon="kpi.icon" :color="kpi.color" size="18" />
            </div>
            <v-chip
              size="x-small"
              rounded="lg"
              :color="kpi.changePositive ? 'success' : 'error'"
              variant="tonal"
            >
              <v-icon start size="10">
                {{
                  kpi.changePositive ? 'mdi-trending-up' : 'mdi-trending-down'
                }}
              </v-icon>
              {{ kpi.change }}
            </v-chip>
          </div>
          <div class="kpi-value">{{ kpi.value }}</div>
          <div class="text-caption text-medium-emphasis mt-1">
            {{ kpi.label }}
          </div>
          <div class="text-caption mt-1" style="opacity: 0.55; font-size: 10px">
            vs {{ periodLabel(previousPeriod) }}: {{ kpi.prev }}
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- ══ Charts Row ═══════════════════════════════════════════════════════ -->
    <v-row dense class="mb-5">
      <!-- Revenue Over Time -->
      <v-col cols="12" md="8">
        <v-card rounded="xl" border elevation="0" class="pa-5">
          <div class="d-flex align-center justify-space-between mb-4">
            <div>
              <div class="text-body-1 font-weight-bold">Revenue Over Time</div>
              <div class="text-caption text-medium-emphasis">
                {{ periodLabel(period) }}
              </div>
            </div>
            <v-btn-toggle
              v-model="chartMode"
              mandatory
              density="compact"
              rounded="lg"
              variant="outlined"
              color="primary"
              size="x-small"
            >
              <v-btn value="revenue" size="x-small">Revenue</v-btn>
              <v-btn value="orders" size="x-small">Orders</v-btn>
            </v-btn-toggle>
          </div>
          <div style="height: 240px">
            <canvas ref="lineChartRef" />
          </div>
        </v-card>
      </v-col>

      <!-- Order Type Breakdown -->
      <v-col cols="12" md="4">
        <v-card rounded="xl" border elevation="0" class="pa-5">
          <div class="text-body-1 font-weight-bold mb-1">Order Types</div>
          <div class="text-caption text-medium-emphasis mb-4">
            Distribution breakdown
          </div>
          <div
            style="
              height: 200px;
              display: flex;
              align-items: center;
              justify-content: center;
            "
          >
            <canvas ref="donutChartRef" />
          </div>
          <!-- Legend -->
          <div class="mt-3">
            <div
              v-for="t in orderTypeStats"
              :key="t.label"
              class="d-flex align-center justify-space-between mb-1"
            >
              <div class="d-flex align-center gap-2">
                <div class="legend-dot" :style="`background:${t.color}`" />
                <span class="text-caption">{{ t.label }}</span>
              </div>
              <span class="text-caption font-weight-bold">{{ t.count }}</span>
            </div>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- ══ Payment + Status Bar ══════════════════════════════════════════════ -->
    <v-row dense class="mb-5">
      <!-- Payment Methods -->
      <v-col cols="12" md="6">
        <v-card rounded="xl" border elevation="0" class="pa-5">
          <div class="text-body-1 font-weight-bold mb-4">Payment Methods</div>
          <div style="height: 200px">
            <canvas ref="barChartRef" />
          </div>
        </v-card>
      </v-col>

      <!-- Status Summary -->
      <v-col cols="12" md="6">
        <v-card rounded="xl" border elevation="0" class="pa-5">
          <div class="text-body-1 font-weight-bold mb-4">Order Status</div>
          <div class="status-list">
            <div
              v-for="s in statusStats"
              :key="s.status"
              class="status-row mb-3"
            >
              <div class="d-flex justify-space-between mb-1">
                <div class="d-flex align-center gap-2">
                  <v-icon :icon="s.icon" :color="s.color" size="16" />
                  <span class="text-body-2 text-capitalize">
                    {{ s.status }}
                  </span>
                </div>
                <span class="text-body-2 font-weight-bold">{{ s.count }}</span>
              </div>
              <v-progress-linear
                :model-value="s.pct"
                :color="s.color"
                rounded
                height="6"
                bg-color="grey-lighten-3"
              />
            </div>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- ══ Period Comparison Banner ══════════════════════════════════════════ -->
    <v-card
      rounded="xl"
      border
      elevation="0"
      class="pa-5 mb-5 comparison-banner"
    >
      <div class="text-body-1 font-weight-bold mb-4">
        Period Comparison
        <span class="text-caption text-medium-emphasis ml-2">
          {{ periodLabel(period) }} vs {{ periodLabel(previousPeriod) }}
        </span>
      </div>
      <v-row dense>
        <v-col v-for="c in comparisonRows" :key="c.label" cols="6" sm="3">
          <div class="compare-cell pa-3 rounded-lg">
            <div class="text-caption text-medium-emphasis mb-2">
              {{ c.label }}
            </div>
            <div class="d-flex align-end gap-2 mb-1">
              <span class="text-h6 font-weight-bold">{{ c.current }}</span>
              <v-chip
                size="x-small"
                rounded="lg"
                :color="c.up ? 'success' : 'error'"
                variant="tonal"
                class="mb-1"
              >
                {{ c.up ? '▲' : '▼' }} {{ c.diff }}
              </v-chip>
            </div>
            <div class="text-caption" style="opacity: 0.5">
              Prev: {{ c.previous }}
            </div>
          </div>
        </v-col>
      </v-row>
    </v-card>

    <!-- ══ Filters + Table ══════════════════════════════════════════════════ -->
    <v-card rounded="xl" border elevation="0" class="mb-4">
      <div class="pa-4 d-flex align-center gap-3 flex-wrap border-b">
        <v-text-field
          v-model="filters.search"
          placeholder="Search order #, customer..."
          variant="outlined"
          density="compact"
          rounded="lg"
          hide-details
          clearable
          prepend-inner-icon="mdi-magnify"
          style="max-width: 260px"
          @update:model-value="onSearch"
        />
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
          style="max-width: 150px"
          @update:model-value="loadOrders"
        />
        <v-select
          v-model="filters.order_type"
          :items="orderTypeOptions"
          item-title="label"
          item-value="value"
          placeholder="Type"
          variant="outlined"
          density="compact"
          rounded="lg"
          hide-details
          clearable
          style="max-width: 150px"
          @update:model-value="loadOrders"
        />
        <v-spacer />
        <v-btn
          variant="tonal"
          rounded="lg"
          size="small"
          icon="mdi-filter-off-outline"
          @click="resetFilters"
        />
      </div>

      <v-data-table-server
        :headers="headers"
        :items="orders"
        :items-length="pagination?.total ?? 0"
        :loading="tableLoading"
        :items-per-page="filters.per_page"
        :page="filters.page"
        item-value="id"
        @update:page="
          p => {
            filters.page = p
            loadOrders()
          }
        "
        @update:items-per-page="
          p => {
            filters.per_page = p
            filters.page = 1
            loadOrders()
          }
        "
      >
        <template #item.order_number="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <v-avatar
              :color="typeColor(item.order_type)"
              size="30"
              rounded="md"
              variant="tonal"
            >
              <v-icon :icon="typeIcon(item.order_type)" size="14" />
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-bold">
                {{ item.order_number }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.branch?.name }}
              </div>
            </div>
          </div>
        </template>

        <template #item.customer="{ item }">
          <div v-if="item.customer">
            <div class="text-body-2">{{ item.customer.name }}</div>
            <div class="text-caption text-medium-emphasis">
              {{ item.customer.phone }}
            </div>
          </div>
          <span v-else class="text-caption text-medium-emphasis">Walk-in</span>
        </template>

        <template #item.items_count="{ item }">
          <v-chip size="x-small" variant="tonal" rounded="lg">
            {{ item.items?.length ?? 0 }} items
          </v-chip>
        </template>

        <template #item.total_amount="{ item }">
          <span class="font-weight-bold text-body-2">
            {{ fmt(item.total_amount) }}
          </span>
        </template>

        <template #item.payment_method="{ item }">
          <v-chip
            v-if="item.payment_method"
            size="x-small"
            rounded="lg"
            variant="tonal"
            :color="payColor(item.payment_method)"
          >
            {{ item.payment_method?.replace('_', ' ') }}
          </v-chip>
          <span v-else class="text-caption text-medium-emphasis">—</span>
        </template>

        <template #item.status="{ item }">
          <v-chip
            size="small"
            rounded="lg"
            variant="tonal"
            :color="statusColor(item.status)"
          >
            {{ item.status }}
          </v-chip>
        </template>

        <template #item.created_at="{ item }">
          <div class="text-body-2">{{ fmtDate(item.created_at) }}</div>
          <div class="text-caption text-medium-emphasis">
            {{ fmtTime(item.created_at) }}
          </div>
        </template>

        <template #item.actions="{ item }">
          <v-btn
            icon="mdi-eye-outline"
            size="small"
            variant="text"
            color="primary"
            @click="viewOrder(item)"
          />
        </template>

        <template #top>
          <div class="d-flex align-center justify-space-between px-4 py-3">
            <div class="text-caption text-medium-emphasis">
              {{ pagination?.total ?? 0 }} orders
            </div>
            <div class="text-body-2 font-weight-bold text-success">
              Total: {{ fmt(stats?.total_revenue ?? 0) }}
            </div>
          </div>
        </template>
      </v-data-table-server>
    </v-card>

    <OrderDetailDialog v-model="detailDialog" :order-id="selectedOrderId" />
  </div>
</template>

<script setup>
  import { ref, computed, onMounted, watch, nextTick } from 'vue'
  import { useOrderStore } from '@/stores/orderStore'
  import { useBranchStore } from '@/stores/branchStore'

  import { useAppUtils } from '@/composables/useAppUtils'
  import OrderDetailDialog from '@/components/orders/OrderDetailDialog.vue'
  import BranchFilterBar from '@/components/common/BranchFilterBar.vue'
  import {
    Chart,
    LineElement,
    BarElement,
    ArcElement,
    PointElement,
    LineController,
    BarController,
    DoughnutController,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
    Filler
  } from 'chart.js'

  Chart.register(
    LineElement,
    BarElement,
    ArcElement,
    PointElement,
    LineController,
    BarController,
    DoughnutController,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
    Filler
  )

  const { notif } = useAppUtils()
  const branchStore = useBranchStore()
  const orderStore = useOrderStore()
  // ── Refs ──────────────────────────────────────────────────────────────────────
  const lineChartRef = ref(null)
  const donutChartRef = ref(null)
  const barChartRef = ref(null)
  let lineChart = null
  let donutChart = null
  let barChart = null

  const period = ref('today')
  const chartMode = ref('revenue')
  const exporting = ref(false)
  const tableLoading = ref(false)

  const stats = ref(null)
  const prevStats = ref(null)
  const chartData = ref([]) // [{label, revenue, orders}]
  const orders = ref([])
  const pagination = ref(null)

  const detailDialog = ref(false)
  const selectedOrderId = ref(null)

  const filters = ref({
    branch_ids: [],
    search: '',
    status: null,
    order_type: null,
    date_from: null,
    date_to: null,
    per_page: 10,
    page: 1
  })

  // ── Period helpers ─────────────────────────────────────────────────────────────
  const previousPeriod = computed(
    () =>
      ({
        today: 'yesterday',
        yesterday: 'day_before',
        week: 'last_week',
        month: 'last_month',
        last_month: 'month_before',
        custom: 'prev_custom'
      })[period.value] ?? 'yesterday'
  )

  const periodLabel = p =>
    ({
      today: 'Today',
      yesterday: 'Yesterday',
      week: 'This Week',
      month: 'This Month',
      last_month: 'Last Month',
      day_before: '2 Days Ago',
      last_week: 'Last Week',
      month_before: '2 Months Ago',
      custom: 'Custom Range',
      prev_custom: 'Previous Range'
    })[p] ?? p

  const periodDates = p => {
    const today = new Date()
    const fmt = d => d.toISOString().slice(0, 10)
    const add = (d, n) => {
      const x = new Date(d)
      x.setDate(x.getDate() + n)
      return x
    }

    switch (p) {
      case 'today':
        return { from: fmt(today), to: fmt(today) }
      case 'yesterday': {
        const y = add(today, -1)
        return { from: fmt(y), to: fmt(y) }
      }
      case 'week': {
        const mon = new Date(today)
        mon.setDate(today.getDate() - today.getDay() + 1)
        return { from: fmt(mon), to: fmt(today) }
      }
      case 'month': {
        const first = new Date(today.getFullYear(), today.getMonth(), 1)
        return { from: fmt(first), to: fmt(today) }
      }
      case 'last_month': {
        const first = new Date(today.getFullYear(), today.getMonth() - 1, 1)
        const last = new Date(today.getFullYear(), today.getMonth(), 0)
        return { from: fmt(first), to: fmt(last) }
      }
      case 'custom':
        return { from: filters.value.date_from, to: filters.value.date_to }
      default:
        return { from: fmt(today), to: fmt(today) }
    }
  }

  const previousPeriodDates = computed(() => {
    const cur = periodDates(period.value)
    if (!cur.from || !cur.to) return cur

    const from = new Date(cur.from)
    const to = new Date(cur.to)
    const days = Math.round((to - from) / 86400000) + 1

    const pFrom = new Date(from)
    pFrom.setDate(pFrom.getDate() - days)
    const pTo = new Date(to)
    pTo.setDate(pTo.getDate() - days)

    return {
      from: pFrom.toISOString().slice(0, 10),
      to: pTo.toISOString().slice(0, 10)
    }
  })

  // ── KPI cards ──────────────────────────────────────────────────────────────────
  const kpiCards = computed(() => {
    const s = stats.value
    const ps = prevStats.value
    if (!s) return []

    const pct = (a, b) => (b ? (((a - b) / b) * 100).toFixed(1) + '%' : '—')
    const up = (a, b) => a >= b

    return [
      {
        label: 'Revenue',
        value: fmt(s.total_revenue ?? 0),
        prev: fmt(ps?.total_revenue ?? 0),
        change: pct(s.total_revenue, ps?.total_revenue),
        changePositive: up(s.total_revenue, ps?.total_revenue),
        icon: 'mdi-cash-multiple',
        color: 'success',
        bg: '#e8f5e9'
      },
      {
        label: 'Orders',
        value: s.total_orders ?? 0,
        prev: ps?.total_orders ?? 0,
        change: pct(s.total_orders, ps?.total_orders),
        changePositive: up(s.total_orders, ps?.total_orders),
        icon: 'mdi-receipt-text-outline',
        color: 'primary',
        bg: '#e3f2fd'
      },
      {
        label: 'Avg Order Value',
        value: fmt(s.total_orders ? s.total_revenue / s.total_orders : 0),
        prev: fmt(ps?.total_orders ? ps.total_revenue / ps.total_orders : 0),
        change: pct(
          s.total_orders ? s.total_revenue / s.total_orders : 0,
          ps?.total_orders ? ps.total_revenue / ps.total_orders : 0
        ),
        changePositive: up(
          s.total_orders ? s.total_revenue / s.total_orders : 0,
          ps?.total_orders ? ps?.total_revenue / ps?.total_orders : 0
        ),
        icon: 'mdi-tag-outline',
        color: 'info',
        bg: '#e1f5fe'
      },
      {
        label: 'Completed',
        value: s.completed ?? 0,
        prev: ps?.completed ?? 0,
        change: pct(s.completed, ps?.completed),
        changePositive: up(s.completed, ps?.completed),
        icon: 'mdi-check-circle-outline',
        color: 'warning',
        bg: '#fff8e1'
      }
    ]
  })

  // ── Status stats ───────────────────────────────────────────────────────────────
  const statusStats = computed(() => {
    const s = stats.value
    if (!s) return []
    const total = s.total_orders || 1
    return [
      {
        status: 'completed',
        count: s.completed ?? 0,
        color: 'success',
        icon: 'mdi-check-circle',
        pct: ((s.completed ?? 0) / total) * 100
      },
      {
        status: 'pending',
        count: s.pending ?? 0,
        color: 'warning',
        icon: 'mdi-clock-outline',
        pct: ((s.pending ?? 0) / total) * 100
      },
      {
        status: 'cancelled',
        count: s.cancelled ?? 0,
        color: 'error',
        icon: 'mdi-close-circle',
        pct: ((s.cancelled ?? 0) / total) * 100
      }
    ]
  })

  // ── Order type stats ───────────────────────────────────────────────────────────
  const orderTypeStats = ref([])

  // ── Comparison rows ────────────────────────────────────────────────────────────
  const comparisonRows = computed(() => {
    const s = stats.value
    const ps = prevStats.value
    if (!s || !ps) return []

    const diff = (a, b, isCurrency = false) => {
      const d = a - b
      return isCurrency ? fmt(Math.abs(d)) : Math.abs(d)
    }
    const up = (a, b) => a >= b

    return [
      {
        label: 'Revenue',
        current: fmt(s.total_revenue ?? 0),
        previous: fmt(ps.total_revenue ?? 0),
        diff: fmt(Math.abs((s.total_revenue ?? 0) - (ps.total_revenue ?? 0))),
        up: up(s.total_revenue, ps.total_revenue)
      },
      {
        label: 'Orders',
        current: s.total_orders ?? 0,
        previous: ps.total_orders ?? 0,
        diff: Math.abs((s.total_orders ?? 0) - (ps.total_orders ?? 0)),
        up: up(s.total_orders, ps.total_orders)
      },
      {
        label: 'Completed',
        current: s.completed ?? 0,
        previous: ps.completed ?? 0,
        diff: Math.abs((s.completed ?? 0) - (ps.completed ?? 0)),
        up: up(s.completed, ps.completed)
      },
      {
        label: 'Cancelled',
        current: s.cancelled ?? 0,
        previous: ps.cancelled ?? 0,
        diff: Math.abs((s.cancelled ?? 0) - (ps.cancelled ?? 0)),
        up: !up(s.cancelled, ps.cancelled) // fewer is better
      }
    ]
  })

  // ── Options ───────────────────────────────────────────────────────────────────
  const statusOptions = [
    { value: null, label: 'All Status' },
    { value: 'pending', label: 'Pending' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'preparing', label: 'Preparing' },
    { value: 'ready', label: 'Ready' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' }
  ]
  const orderTypeOptions = [
    { value: null, label: 'All Types' },
    { value: 'dine_in', label: 'Dine In' },
    { value: 'takeaway', label: 'Takeaway' },
    { value: 'delivery', label: 'Delivery' },
    { value: 'walk_in', label: 'Walk In' }
  ]
  const headers = [
    { title: 'Order', key: 'order_number', sortable: false },
    { title: 'Customer', key: 'customer', sortable: false },
    { title: 'Items', key: 'items_count', sortable: false },
    { title: 'Total', key: 'total_amount', sortable: true },
    { title: 'Payment', key: 'payment_method', sortable: false },
    { title: 'Status', key: 'status', sortable: false },
    { title: 'Date', key: 'created_at', sortable: true },
    { title: '', key: 'actions', sortable: false, width: '48' }
  ]

  // ── Helpers ───────────────────────────────────────────────────────────────────
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
  const fmtTime = v =>
    v
      ? new Date(v).toLocaleTimeString('en-US', {
          hour: '2-digit',
          minute: '2-digit'
        })
      : ''
  const statusColor = s =>
    ({
      pending: 'warning',
      confirmed: 'info',
      preparing: 'info',
      ready: 'success',
      completed: 'success',
      cancelled: 'error'
    })[s] ?? 'grey'
  const typeColor = t =>
    ({
      dine_in: 'primary',
      takeaway: 'info',
      delivery: 'warning',
      walk_in: 'success'
    })[t] ?? 'grey'
  const typeIcon = t =>
    ({
      dine_in: 'mdi-silverware',
      takeaway: 'mdi-bag-personal',
      delivery: 'mdi-moped',
      walk_in: 'mdi-walk'
    })[t] ?? 'mdi-cart'
  const payColor = p =>
    ({
      cash: 'success',
      card: 'primary',
      qr: 'info',
      store_credit: 'warning',
      credit_term: 'error'
    })[p] ?? 'grey'

  // ── Data loading ──────────────────────────────────────────────────────────────
  const onPeriodChange = (val) => {
    period.value = val   
    if (period.value !== 'custom') {
      const dates = periodDates(val)
      filters.value.date_from = dates.from
      filters.value.date_to = dates.to
      filters.value.page = 1
      loadAll()
    }
  }

  const loadAll = async () => {
    await Promise.all([
      loadStats(),
      loadOrders(),
      loadChartData(),
      branchStore.fetchBranches()
    ])
  }

  const loadStats = async () => {
    try {
      const dates = periodDates(period.value)
      const prevDates = previousPeriodDates.value

      const [curRes, prevRes] = await Promise.all([
        orderStore.getAllOrdersReport({
          date_from: dates.from,
          date_to: dates.to,
          per_page: 1
        }),
        orderStore.getAllOrdersReport({
          date_from: prevDates.from,
          date_to: prevDates.to,
          per_page: 1
        })
      ])
      stats.value = curRes.data.stats
      prevStats.value = prevRes.data.stats
    } catch (e) {
      console.error(e)
    }
  }

  const loadOrders = async () => {
    tableLoading.value = true
    try {
      const dates = periodDates(period.value)
      const res = await orderStore.getAllOrdersReport({
        ...filters.value,
        date_from: filters.value.date_from ?? dates.from,
        date_to: filters.value.date_to ?? dates.to
      })
      orders.value = res.data.data.data
      pagination.value = {
        current_page: res.data.data.current_page,
        last_page: res.data.data.last_page,
        per_page: res.data.data.per_page,
        total: res.data.data.total
      }

      // Build type stats from current results
      buildTypeStats(orders.value)
    } catch (e) {
      console.error(e)
    } finally {
      tableLoading.value = false
    }
  }

  const loadChartData = async () => {
    try {
      // Fetch all orders for chart (no pagination)
      const dates = periodDates(period.value)
      const res = await orderStore.getAllOrdersReport({
        date_from: dates.from,
        date_to: dates.to,
        per_page: 9999
      })
      const allOrders = res.data.data.data
      buildChartData(allOrders, dates.from, dates.to)
      buildPaymentStats(allOrders)
    } catch (e) {
      console.error(e)
    }
  }

  const buildChartData = (allOrders, from, to) => {
    // Group by day
    const map = {}
    const cur = new Date(from)
    const end = new Date(to)
    while (cur <= end) {
      const key = cur.toISOString().slice(0, 10)
      map[key] = { label: key, revenue: 0, orders: 0 }
      cur.setDate(cur.getDate() + 1)
    }
    for (const o of allOrders) {
      const key = o.created_at?.slice(0, 10)
      if (map[key] && o.status !== 'cancelled') {
        map[key].revenue += parseFloat(o.total_amount ?? 0)
        map[key].orders++
      }
    }
    chartData.value = Object.values(map)
    nextTick(() => drawLineChart())
  }

  const buildTypeStats = allOrders => {
    const COLORS = {
      dine_in: '#3b82f6',
      takeaway: '#06b6d4',
      delivery: '#f59e0b',
      walk_in: '#10b981'
    }
    const map = {}
    for (const o of allOrders) {
      const t = o.order_type ?? 'other'
      map[t] = (map[t] ?? 0) + 1
    }
    orderTypeStats.value = Object.entries(map).map(([key, count]) => ({
      label: key.replace('_', ' '),
      count,
      color: COLORS[key] ?? '#94a3b8'
    }))
    nextTick(() => drawDonutChart())
  }

  const buildPaymentStats = allOrders => {
    const map = {}
    for (const o of allOrders) {
      const p = o.payment_method ?? 'unknown'
      map[p] = (map[p] ?? 0) + parseFloat(o.total_amount ?? 0)
    }
    nextTick(() => drawBarChart(map))
  }

  // ── Chart drawing ──────────────────────────────────────────────────────────────
  const drawLineChart = () => {
    if (!lineChartRef.value) return
    if (lineChart) lineChart.destroy()

    const labels = chartData.value.map(d => {
      const dt = new Date(d.label)
      return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
    })
    const values =
      chartMode.value === 'revenue'
        ? chartData.value.map(d => d.revenue)
        : chartData.value.map(d => d.orders)

    lineChart = new Chart(lineChartRef.value, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: chartMode.value === 'revenue' ? 'Revenue ($)' : 'Orders',
            data: values,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59,130,246,0.08)',
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: '#3b82f6',
            borderWidth: 2
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { mode: 'index', intersect: false }
        },
        scales: {
          x: { grid: { display: false }, ticks: { maxTicksLimit: 7 } },
          y: { grid: { color: '#f1f5f9' }, beginAtZero: true }
        }
      }
    })
  }

  const drawDonutChart = () => {
    if (!donutChartRef.value) return
    if (donutChart) donutChart.destroy()

    const data = orderTypeStats.value
    donutChart = new Chart(donutChartRef.value, {
      type: 'doughnut',
      data: {
        labels: data.map(d => d.label),
        datasets: [
          {
            data: data.map(d => d.count),
            backgroundColor: data.map(d => d.color),
            borderWidth: 0
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: { legend: { display: false } }
      }
    })
  }

  const drawBarChart = paymentMap => {
    if (!barChartRef.value) return
    if (barChart) barChart.destroy()

    const labels = Object.keys(paymentMap).map(k => k.replace('_', ' '))
    const values = Object.values(paymentMap)
    const colors = ['#10b981', '#3b82f6', '#06b6d4', '#f59e0b', '#ef4444']

    barChart = new Chart(barChartRef.value, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Revenue ($)',
            data: values,
            backgroundColor: labels.map((_, i) => colors[i % colors.length]),
            borderRadius: 6,
            borderSkipped: false
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false } },
          y: { grid: { color: '#f1f5f9' }, beginAtZero: true }
        }
      }
    })
  }

  watch(chartMode, () => drawLineChart())

  // ── Actions ───────────────────────────────────────────────────────────────────
  let searchTimer = null
  const onSearch = () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => loadOrders(), 400)
  }

  const resetFilters = () => {
    filters.value = {
      search: '',
      status: null,
      order_type: null,
      date_from: null,
      date_to: null,
      per_page: 15,
      page: 1
    }
    loadOrders()
  }

  const viewOrder = order => {
    selectedOrderId.value = order.id
    detailDialog.value = true
  }

  const exportExcel = async () => {
    exporting.value = true
    try {
      const dates = periodDates(period.value)
      const res = await orderStore.exportOrders({
        ...filters.value,
        date_from: filters.value.date_from ?? dates.from,
        date_to: filters.value.date_to ?? dates.to
      })
      const url = URL.createObjectURL(new Blob([res.data]))
      const link = document.createElement('a')
      link.href = url
      link.download = `Order-Report-${new Date().toISOString().slice(0, 10)}.xlsx`
      link.click()
      URL.revokeObjectURL(url)
      notif('Excel exported', { type: 'success' })
    } catch {
      notif('Export failed', { type: 'error' })
    } finally {
      exporting.value = false
    }
  }

  onMounted(() => {
    onPeriodChange()
  })
</script>

<style scoped>

  .period-tabs {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  /* KPI cards */
  .kpi-card {
    transition: box-shadow 0.2s;
  }
  .kpi-card:hover {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.07) !important;
  }
  .kpi-icon-wrap {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .kpi-value {
    font-size: 1.45rem;
    font-weight: 700;
    line-height: 1.2;
    letter-spacing: -0.5px;
  }

  /* Comparison banner */
  .comparison-banner {
    background: linear-gradient(135deg, #f8faff 0%, #f0f4ff 100%);
  }
  .compare-cell {
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(0, 0, 0, 0.06);
  }

  /* Legend dot */
  .legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  .gap-2 {
    gap: 8px;
  }
  .gap-3 {
    gap: 12px;
  }
  .border-b {
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
  }
</style>
