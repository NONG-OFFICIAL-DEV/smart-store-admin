<template>
  <div>
    <custom-title
      icon="mdi-finance"
      :title="t('purchase_report.title')"
      :subtitle="t('purchase_report.subtitle')"
    ></custom-title>

    <!-- Branch + Period filter bar -->
    <BranchFilterBar
      v-model="branchId"
      :branches="branchStore.branches?.data || branchStore.branches || []"
      :period="period"
      :date-from="dateFrom"
      :date-to="dateTo"
      @period-change="onPeriodChange"
      @date-change="onDateChange"
    />

    <!-- Summary cards -->
    <v-row dense class="mb-5">
      <v-col cols="6" sm="3">
        <v-card rounded="xl" border elevation="0" class="pa-4">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption text-medium-emphasis">{{ t('purchase_report.summary.total_spent') }}</span>
            <v-avatar size="28" color="primary" variant="tonal" rounded="lg">
              <v-icon icon="mdi-cash-multiple" size="14" />
            </v-avatar>
          </div>
          <div class="text-h5 font-weight-black text-primary">
            {{ format(report.summary?.total_spent) }}
          </div>
          <div class="text-caption text-medium-emphasis mt-1">
            {{ report.summary?.total_pos ?? 0 }} {{ t('purchase_report.orders_suffix') }}
          </div>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card rounded="xl" border elevation="0" class="pa-4">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption text-medium-emphasis">{{ t('purchase_report.summary.avg_po') }}</span>
            <v-avatar size="28" color="indigo" variant="tonal" rounded="lg">
              <v-icon icon="mdi-calculator" size="14" />
            </v-avatar>
          </div>
          <div class="text-h5 font-weight-black text-indigo">
            {{ format(report.summary?.avg_po_value) }}
          </div>
          <div class="text-caption text-medium-emphasis mt-1">
            {{ report.summary?.total_pos ?? 0 }} {{ t('purchase_report.orders_suffix') }}
          </div>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card rounded="xl" border elevation="0" class="pa-4">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption text-medium-emphasis">{{ t('purchase_report.summary.received') }}</span>
            <v-avatar size="28" color="success" variant="tonal" rounded="lg">
              <v-icon icon="mdi-check-circle-outline" size="14" />
            </v-avatar>
          </div>
          <div class="text-h5 font-weight-black text-success">
            {{ report.summary?.received_count ?? 0 }}
          </div>
          <div class="text-caption text-medium-emphasis mt-1">
            +{{ report.summary?.partial_count ?? 0 }} {{ t('purchase_report.partial_suffix') }}
          </div>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card rounded="xl" border elevation="0" class="pa-4">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption text-medium-emphasis">{{ t('purchase_report.summary.pending') }}</span>
            <v-avatar size="28" color="warning" variant="tonal" rounded="lg">
              <v-icon icon="mdi-clock-outline" size="14" />
            </v-avatar>
          </div>
          <div class="text-h5 font-weight-black text-warning">
            {{ report.summary?.pending_count ?? 0 }}
          </div>
          <div class="text-caption text-medium-emphasis mt-1">
            {{ report.summary?.cancelled_count ?? 0 }} {{ t('purchase_report.cancelled_suffix') }}
          </div>
        </v-card>
      </v-col>
    </v-row>

    <v-row dense>
      <!-- Spending chart -->
      <v-col cols="12" lg="8">
        <v-card rounded="xl" border elevation="0" class="mb-4">
          <div class="pa-5 pb-3 text-body-1 font-weight-bold">
            {{ t('purchase_report.chart.spending') }}
          </div>
          <v-divider />
          <div class="pa-4">
            <canvas ref="chartCanvas" height="260" />
          </div>
        </v-card>
      </v-col>

      <!-- By supplier -->
      <v-col cols="12" lg="4">
        <v-card rounded="xl" border elevation="0" class="mb-4">
          <div class="pa-5 pb-3 text-body-1 font-weight-bold">{{ t('purchase_report.chart.by_supplier') }}</div>
          <v-divider />
          <v-card-text class="pa-4">
            <div
              v-for="(s, i) in report.by_supplier"
              :key="s.supplier_id"
              class="mb-3"
            >
              <div class="d-flex justify-space-between align-center mb-1">
                <div class="d-flex align-center gap-2">
                  <v-avatar
                    size="22"
                    color="primary"
                    variant="tonal"
                    rounded="lg"
                    class="text-caption font-weight-black"
                  >
                    {{ i + 1 }}
                  </v-avatar>
                  <span class="text-body-2 font-weight-medium">
                    {{ s.supplier_name }}
                  </span>
                </div>
                <div class="text-right">
                  <div class="text-body-2 font-weight-bold">
                    {{ format(s.total_spent) }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ s.po_count }} {{ t('purchase_report.pos_suffix') }}
                  </div>
                </div>
              </div>
              <v-progress-linear
                :model-value="(s.total_spent / maxSupplierSpend) * 100"
                color="primary"
                height="4"
                rounded
                bg-color="grey-lighten-3"
              />
            </div>
            <div
              v-if="!report.by_supplier?.length"
              class="text-caption text-medium-emphasis text-center py-6"
            >
              {{ t('table.no_data') }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row dense>
      <!-- Top purchased products -->
      <v-col cols="12" lg="6">
        <v-card rounded="xl" border elevation="0">
          <div class="pa-5 pb-3 text-body-1 font-weight-bold">
            {{ t('purchase_report.chart.top_products') }}
          </div>
          <v-divider />
          <v-data-table
            :headers="productHeaders"
            :items="report.top_products ?? []"
            :items-per-page="10"
            density="compact"
            item-value="product_id"
          >
            <template #item.product_name="{ item }">
              <span class="text-body-2 font-weight-medium">
                {{ item.product_name }}
              </span>
            </template>
            <template #item.total_ordered="{ item }">
              <span class="text-body-2">{{ item.total_ordered }}</span>
            </template>
            <template #item.total_received="{ item }">
              <v-chip
                size="x-small"
                rounded="lg"
                variant="tonal"
                :color="
                  item.total_received >= item.total_ordered
                    ? 'success'
                    : 'warning'
                "
              >
                {{ item.total_received }}
              </v-chip>
            </template>
            <template #item.total_cost="{ item }">
              <span class="font-weight-bold text-primary">
                {{ format(item.total_cost) }}
              </span>
            </template>
          </v-data-table>
        </v-card>
      </v-col>

      <!-- Recent POs -->
      <v-col cols="12" lg="6">
        <v-card rounded="xl" border elevation="0">
          <div class="pa-5 pb-3 text-body-1 font-weight-bold">
            {{ t('purchase_report.chart.recent_pos') }}
          </div>
          <v-divider />
          <v-data-table
            :headers="poHeaders"
            :items="report.recent_pos ?? []"
            :items-per-page="10"
            density="compact"
            item-value="id"
          >
            <template #item.po_number="{ item }">
              <span class="text-caption font-weight-bold">
                {{ item.po_number }}
              </span>
            </template>
            <template #item.total_amount="{ item }">
              <span class="font-weight-bold">{{ format(item.total_amount) }}</span>
            </template>
            <template #item.status="{ item }">
              <v-chip
                size="x-small"
                rounded="lg"
                variant="tonal"
                :color="statusColor(item.status)"
              >
                {{ item.status.replace('_', ' ') }}
              </v-chip>
            </template>
            <template #item.created_at="{ item }">
              <span class="text-caption text-medium-emphasis">
                {{ fmtDate(item.created_at) }}
              </span>
            </template>
          </v-data-table>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script setup>
  import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue'
  import {
    Chart,
    BarElement,
    BarController,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend
  } from 'chart.js'

  // Register only what you need (tree-shakeable)
  Chart.register(
    BarElement,
    BarController,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend
  )

  import { useAuthStore } from '@/stores/authStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import { useBranchStore } from '@/stores/branchStore'
  import { useI18n } from 'vue-i18n'
  import { useCurrency } from '@/composables/useCurrency_v2'
  import api from '@/api/api'
  import BranchFilterBar from '@/components/common/BranchFilterBar.vue'
  import { useDate } from '@/composables/useDate'

  const authStore = useAuthStore()
  const branchStore = useBranchStore()
  const { notif } = useAppUtils()
  const { t } = useI18n()
  const { format ,currencySymbol} = useCurrency()
  const { formatShortDate: fmtDate } = useDate()

  // ── State ─────────────────────────────────────────────────────────────────
  const loading = ref(false)
  const period = ref('month')
  const dateFrom = ref('')
  const dateTo = ref('')
  const branchId = ref(null) // ← reactive ref, not plain array
  const chartCanvas = ref(null)
  const report = ref({
    summary: {},
    by_supplier: [],
    chart: [],
    top_products: [],
    recent_pos: []
  })
  let chartInstance = null

  // ── Period helpers ────────────────────────────────────────────────────────
  const periodDates = p => {
    const now = new Date()
    const pad = n => String(n).padStart(2, '0')
    const ymd = d =>
      `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
    if (p === 'today') {
      const t = ymd(now)
      return { from: t, to: t }
    }
    if (p === 'yesterday') {
      const y = new Date(now)
      y.setDate(now.getDate() - 1)
      const t = ymd(y)
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
    if (p === 'last_month') {
      const first = new Date(now.getFullYear(), now.getMonth() - 1, 1)
      const last = new Date(now.getFullYear(), now.getMonth(), 0)
      return { from: ymd(first), to: ymd(last) }
    }
    if (p === 'year') {
      return { from: `${now.getFullYear()}-01-01`, to: ymd(now) }
    }
    return { from: dateFrom.value, to: dateTo.value }
  }

  // ── BranchFilterBar event handlers ────────────────────────────────────────
  const onPeriodChange = p => {
    period.value = p
    if (p !== 'custom') {
      const d = periodDates(p)
      dateFrom.value = d.from
      dateTo.value = d.to
      load()
    }
  }

  const onDateChange = ({ from, to }) => {
    dateFrom.value = from
    dateTo.value = to
    period.value = 'custom'
    if (from && to) load()
  }

  // ── Computed ──────────────────────────────────────────────────────────────
  const maxSupplierSpend = computed(() =>
    Math.max(
      ...(report.value.by_supplier ?? []).map(s => parseFloat(s.total_spent)),
      1
    )
  )

  // ── Table headers ─────────────────────────────────────────────────────────
  const productHeaders = computed(() => [
    { title: t('purchase_report.table.product'), key: 'product_name', sortable: false },
    { title: t('purchase_report.table.ordered'), key: 'total_ordered', sortable: true },
    { title: t('purchase_report.summary.received'), key: 'total_received', sortable: false },
    { title: t('purchase_report.table.cost'), key: 'total_cost', sortable: true }
  ])

  const poHeaders = computed(() => [
    { title: t('po.table.po_number'), key: 'po_number', sortable: false },
    { title: t('po.table.supplier'), key: 'supplier', sortable: false },
    { title: t('po.table.total'), key: 'total_amount', sortable: false },
    { title: t('form.status'), key: 'status', sortable: false },
    { title: t('form.date'), key: 'created_at', sortable: false }
  ])

  // ── Helpers ───────────────────────────────────────────────────────────────
  const statusColor = s =>
    ({
      draft: 'grey',
      submitted: 'blue',
      confirmed: 'indigo',
      partially_received: 'warning',
      received: 'success',
      cancelled: 'error'
    })[s] ?? 'grey'


  // ── Chart ─────────────────────────────────────────────────────────────────
  // ── Chart ─────────────────────────────────────────────────────────────────
  const drawChart = () => {
    if (!chartCanvas.value) return
    if (!report.value.chart?.length) return

    if (chartInstance) {
      chartInstance.destroy()
      chartInstance = null
    }

    chartInstance = new Chart(chartCanvas.value, {
      type: 'bar',
      data: {
        labels: report.value.chart.map(c => c.label),
        datasets: [
          {
            label: `Amount Spent (${currencySymbol})`,
            data: report.value.chart.map(c => parseFloat(c.total_spent)),
            backgroundColor: 'rgba(99,102,241,0.7)',
            borderColor: 'rgb(99,102,241)',
            borderWidth: 1,
            borderRadius: 6
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { callback: v => `${format(v)}` }
          }
        }
      }
    })
  }

  // ── Load ──────────────────────────────────────────────────────────────────
  const load = async () => {
    if (!dateFrom.value || !dateTo.value) return
    loading.value = true
    try {
      const res = await api.get('v1/mart/reports/purchases', {
        params: {
          date_from: dateFrom.value,
          date_to: dateTo.value,
          branch_id: branchId.value ?? authStore.branch_id
        }
      })
      report.value = res.data.data
      await nextTick()
      drawChart()
    } catch {
      notif(t('purchase_report.load_failed'), { type: 'error' })
    } finally {
      loading.value = false
    }
  }

  // ── Init ──────────────────────────────────────────────────────────────────
  onMounted(async () => {
    await branchStore.fetchBranches() // ← correct: separate await
    onPeriodChange('month') // ← sets dates + loads
  })
  onUnmounted(() => {
    if (chartInstance) chartInstance.destroy()
  })
</script>

<style scoped>
  .gap-2 {
    gap: 8px;
  }
</style>
