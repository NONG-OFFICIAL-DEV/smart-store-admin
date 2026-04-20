<template>
  <div>
    <custom-title
      icon="mdi-note"
      :title="t('order_report.title')"
      :subtitle="t('order_report.subtitle')"
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
          {{ t('btn.export') }}
        </v-btn>
      </template>
    </custom-title>
    <BranchFilterBar
      v-model="filters.branch_ids"
      :branches="branchStore.branches?.data || branchStore.branches || []"
      :period="period"
      :date-from="filters.date_from"
      :date-to="filters.date_to"
      @period-change="onPeriodChange"
      @date-change="onDateChange"
    />

    <!-- ══ KPI Cards ════════════════════════════════════════════════════════ -->
    <v-row dense class="mb-5">
      <v-col v-for="kpi in kpiCards" :key="kpi.label" cols="6" sm="4">
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
            {{ t('common.vs') }} {{ periodLabel(previousPeriod) }}:
            {{ kpi.prev }}
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- ══ Charts Row ═══════════════════════════════════════════════════════ -->
    <v-row dense class="mb-5">
      <!-- Revenue Over Time -->
      <v-col cols="12" md="12">
        <v-card rounded="xl" border elevation="0" class="pa-5">
          <div class="d-flex align-center justify-space-between mb-4">
            <div>
              <div class="text-body-1 font-weight-bold">
                {{ t('order_report.revenue_over_time') }}
              </div>
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
              <v-btn value="revenue" size="x-small">
                {{ t('order_report.state.revenue') }}
              </v-btn>
              <v-btn value="orders" size="x-small">
                {{ t('order_report.state.orders') }}
              </v-btn>
            </v-btn-toggle>
          </div>
          <div style="height: 240px">
            <canvas ref="lineChartRef" />
          </div>
        </v-card>
      </v-col>
    </v-row>

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
          style="max-width: 250px"
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
            {{ item.items?.length ?? 0 }} {{t('order_report.headers.items')}}
          </v-chip>
        </template>

        <template #item.total_amount="{ item }">
          <span class="font-weight-bold text-body-2">
            {{ format(item.total_amount) }}
          </span>
        </template>

        <template #item.payment_method="{ item }">
          <v-chip
            size="small"
            rounded="lg"
            v-if="getPaymentMethod(item)"
            :color="payColor(getPaymentMethod(item))"
          >
            {{ payLabel(getPaymentMethod(item)) }}
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
      </v-data-table-server>
    </v-card>

    <OrderDetailDialog v-model="detailDialog" :order-id="selectedOrderId" />
  </div>
</template>

<script setup>
  import { ref, computed, onMounted, watch, nextTick } from 'vue'
  import { useOrderStore } from '@/stores/orderStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useI18n } from 'vue-i18n'

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
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  const { format, currencySymbol } = useCurrency()

  const { notif } = useAppUtils()
  const branchStore = useBranchStore()
  const orderStore = useOrderStore()
  const { t } = useI18n()
  // ── Refs ──────────────────────────────────────────────────────────────────────
  const lineChartRef = ref(null)
  const donutChartRef = ref(null)
  const barChartRef = ref(null)
  let lineChart = null
  let donutChart = null
  let barChart = null

  const period = ref('month')
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
      today: t('common.today'),
      yesterday: t('common.yesterday'),
      week: t('common.this_week'),
      month: t('common.this_month'),
      last_month: t('common.last_month'),
      day_before: t('common.day_before'),
      last_week: t('common.last_week'),
      month_before: t('common.month_before'),
      custom: t('common.custom'),
      prev_custom: t('common.prev_custom')
    })[p] ?? p

  const periodDates = p => {
    const today = new Date()
    const format = d => d.toISOString().slice(0, 10)
    const add = (d, n) => {
      const x = new Date(d)
      x.setDate(x.getDate() + n)
      return x
    }

    switch (p) {
      case 'today':
        return { from: format(today), to: format(today) }
      case 'yesterday': {
        const y = add(today, -1)
        return { from: format(y), to: format(y) }
      }
      case 'week': {
        const mon = new Date(today)
        mon.setDate(today.getDate() - today.getDay() + 1)
        return { from: format(mon), to: format(today) }
      }
      case 'month': {
        const first = new Date(today.getFullYear(), today.getMonth(), 1)
        return { from: format(first), to: format(today) }
      }
      case 'last_month': {
        const first = new Date(today.getFullYear(), today.getMonth() - 1, 1)
        const last = new Date(today.getFullYear(), today.getMonth(), 0)
        return { from: format(first), to: format(last) }
      }
      case 'custom':
        return { from: filters.value.date_from, to: filters.value.date_to }
      default:
        return { from: format(today), to: format(today) }
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
        label: t('order_report.state.revenue'),
        value: format(s.total_revenue ?? 0),
        prev: format(ps?.total_revenue ?? 0),
        change: pct(s.total_revenue, ps?.total_revenue),
        changePositive: up(s.total_revenue, ps?.total_revenue),
        icon: 'mdi-cash-multiple',
        color: 'success',
        bg: '#e8f5e9'
      },
      {
        label: t('order_report.state.orders'),
        value: s.total_orders ?? 0,
        prev: ps?.total_orders ?? 0,
        change: pct(s.total_orders, ps?.total_orders),
        changePositive: up(s.total_orders, ps?.total_orders),
        icon: 'mdi-receipt-text-outline',
        color: 'primary',
        bg: '#e3f2fd'
      },
      {
        label: t('order_report.state.avg_order_value'),
        value: format(s.total_orders ? s.total_revenue / s.total_orders : 0),
        prev: format(ps?.total_orders ? ps.total_revenue / ps.total_orders : 0),
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
      }
    ]
  })

  // ── Order type stats ───────────────────────────────────────────────────────────
  const orderTypeStats = ref([])

  // ── Options ───────────────────────────────────────────────────────────────────

  const orderTypeOptions = [
    { value: null, label: 'All Types' },
    { value: 'dine_in', label: 'Dine In' },
    { value: 'takeaway', label: 'Takeaway' },
    { value: 'delivery', label: 'Delivery' },
    { value: 'walk_in', label: 'Walk In' }
  ]
  const headers = [
    { title: t('order_report.headers.order_number'), key: 'order_number', sortable: false },
    { title: t('order_report.headers.customer'), key: 'customer', sortable: false },
    { title: t('order_report.headers.items'), key: 'items_count', sortable: false },
    { title: t('order_report.headers.total'), key: 'total_amount', sortable: true },
    { title: t('order_report.headers.payment'), key: 'payment_method', sortable: false },
    { title: t('order_report.headers.date'), key: 'created_at', sortable: true },
    { title: '', key: 'actions', sortable: false, width: '48' }
  ]

  // ── Helpers ───────────────────────────────────────────────────────────────────

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

  const getPaymentMethod = order =>
    order.payments?.[0]?.payment_method ?? order.payment_method ?? null

  // Normalize to display label
  const payLabel = raw =>
    ({
      cash: 'Cash',
      card: 'Card',
      qr_code: 'QR',
      qr: 'QR',
      online: 'Transfer',
      transfer: 'Transfer'
    })[raw] ??
    raw?.replace('_', ' ') ??
    '—'

  // Color map — covers both old and new enum values
  const payColor = raw =>
    ({
      cash: 'success',
      card: 'primary',
      qr_code: 'info',
      qr: 'info',
      online: 'purple',
      transfer: 'purple',
      store_credit: 'warning',
      credit_term: 'error'
    })[raw] ?? 'grey'

  // ── Data loading ──────────────────────────────────────────────────────────────
  const onPeriodChange = val => {
    period.value = val
    if (period.value !== 'custom') {
      const dates = periodDates(val)
      filters.value.date_from = dates.from
      filters.value.date_to = dates.to
      filters.value.page = 1
      loadAll()
    }
  }
  const onDateChange = ({ from, to }) => {
    filters.value.date_from = from
    filters.value.date_to = to
    period.value = 'custom'
    if (from && to) loadAll()
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
      // Read from payments[] array first, fallback to order.payment_method
      const raw =
        o.payments?.[0]?.payment_method ?? o.payment_method ?? 'unknown'

      // Normalize db enum → display label
      const label =
        {
          cash: 'Cash',
          card: 'Card',
          qr_code: 'QR',
          online: 'Transfer',
          qr: 'QR',
          transfer: 'Transfer'
        }[raw] ?? raw

      map[label] = (map[label] ?? 0) + parseFloat(o.total_amount ?? 0)
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
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label:
              chartMode.value === 'revenue'
                ? `Revenue (${currencySymbol()})`
                : 'Orders',
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
          y: {
            beginAtZero: true,
            ticks: { callback: v => `${format(v)}` }
          },
          x: { grid: { display: false }, ticks: { maxTicksLimit: 7 } }
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
    loadAll()
    // onPeriodChange('month')
  })
</script>

<style scoped>
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
