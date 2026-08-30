<template>
  <div>
    <custom-title
      icon="mdi-cash-multiple"
      :title="t('revenue_report.title')"
      :subtitle="t('revenue_report.subtitle')"
    />

    <BranchFilterBar
      v-model="branchId"
      :branches="branchStore.branches?.data || branchStore.branches || []"
      :period="period"
      :date-from="dateFrom"
      :date-to="dateTo"
      @period-change="onPeriodChange"
      @date-change="onDateChange"
    />

    <v-card rounded="xl" border elevation="0">
      <div class="tab-bar-wrap">
        <v-tabs v-model="activeTab" color="primary" density="comfortable">
          <v-tab value="revenue" prepend-icon="mdi-chart-line">
            {{ t('revenue_report.tabs.revenue') }}
          </v-tab>
          <v-tab value="top_customers" prepend-icon="mdi-account-star-outline">
            {{ t('revenue_report.tabs.top_customers') }}
            <v-chip v-if="customers.length" size="x-small" color="primary" variant="tonal" rounded="pill" class="ml-2">
              {{ customers.length }}
            </v-chip>
          </v-tab>
        </v-tabs>
        <v-progress-circular v-if="loading" indeterminate color="primary" size="18" width="2" class="mr-3" />
      </div>

      <v-divider />

      <v-tabs-window v-model="activeTab">
        <!-- ── REVENUE ── -->
        <v-tabs-window-item value="revenue">
          <div class="pa-4">
            <v-row dense class="mb-4">
              <v-col cols="6" md="4">
                <v-card rounded="xl" border elevation="0" class="kpi-card pa-4">
                  <div class="kpi-header">
                    <span class="text-caption text-medium-emphasis font-weight-medium">
                      {{ t('revenue_report.summary.total_revenue') }}
                    </span>
                    <v-avatar size="30" color="success" variant="tonal" rounded="lg">
                      <v-icon icon="mdi-cash-multiple" size="15" />
                    </v-avatar>
                  </div>
                  <div class="kpi-value text-success">{{ format(summary.total_revenue) }}</div>
                </v-card>
              </v-col>
              <v-col cols="6" md="4">
                <v-card rounded="xl" border elevation="0" class="kpi-card pa-4">
                  <div class="kpi-header">
                    <span class="text-caption text-medium-emphasis font-weight-medium">
                      {{ t('revenue_report.summary.total_orders') }}
                    </span>
                    <v-avatar size="30" color="primary" variant="tonal" rounded="lg">
                      <v-icon icon="mdi-receipt-text-outline" size="15" />
                    </v-avatar>
                  </div>
                  <div class="kpi-value text-primary">{{ summary.total_orders ?? 0 }}</div>
                </v-card>
              </v-col>
              <v-col cols="12" md="4">
                <v-card rounded="xl" border elevation="0" class="kpi-card pa-4">
                  <div class="kpi-header">
                    <span class="text-caption text-medium-emphasis font-weight-medium">
                      {{ t('revenue_report.summary.avg_order_value') }}
                    </span>
                    <v-avatar size="30" color="info" variant="tonal" rounded="lg">
                      <v-icon icon="mdi-tag-outline" size="15" />
                    </v-avatar>
                  </div>
                  <div class="kpi-value text-info">{{ format(summary.avg_order_value) }}</div>
                </v-card>
              </v-col>
            </v-row>

            <v-card rounded="xl" border elevation="0" class="pa-5">
              <div class="text-body-1 font-weight-bold mb-4">
                {{ t('revenue_report.chart.title') }}
              </div>
              <div v-if="daily.length" style="height: 280px">
                <canvas ref="chartRef" />
              </div>
              <div v-else class="d-flex flex-column align-center justify-center pa-10" style="gap: 8px">
                <v-icon icon="mdi-chart-line" size="44" color="grey-lighten-1" />
                <div class="text-body-2 text-medium-emphasis">{{ t('revenue_report.empty') }}</div>
              </div>
            </v-card>
          </div>
        </v-tabs-window-item>

        <!-- ── TOP CUSTOMERS ── -->
        <v-tabs-window-item value="top_customers">
          <div class="pa-4">
            <v-data-table
              :headers="customerHeaders"
              :items="customers"
              :loading="loading"
              item-value="customer_id"
              class="revenue-table"
            >
              <template #item.rank="{ index }">
                <span class="font-weight-bold">{{ index + 1 }}</span>
              </template>
              <template #item.name="{ item }">
                {{ customerName(item) }}
              </template>
              <template #item.email="{ item }">
                {{ item.customer?.email ?? '—' }}
              </template>
              <template #item.order_count="{ item }">
                <v-chip size="small" variant="tonal" color="primary" rounded="lg">{{ item.order_count }}</v-chip>
              </template>
              <template #item.total_spent="{ item }">
                <span class="font-weight-bold">{{ format(item.total_spent) }}</span>
              </template>
              <template #item.avg_order_value="{ item }">
                {{ format(item.avg_order_value) }}
              </template>
              <template #no-data>
                <div class="d-flex flex-column align-center justify-center pa-10" style="gap: 8px">
                  <v-icon icon="mdi-account-off-outline" size="44" color="grey-lighten-1" />
                  <div class="text-body-2 text-medium-emphasis">{{ t('revenue_report.empty') }}</div>
                </div>
              </template>
            </v-data-table>
          </div>
        </v-tabs-window-item>
      </v-tabs-window>
    </v-card>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBranchStore } from '@/stores/branchStore'
import { useAppUtils } from '@/composables/useAppUtils'
import { useCurrency } from '@/composables/useCurrency_v2.js'
import api from '@/api/api'
import BranchFilterBar from '@/components/common/BranchFilterBar.vue'

import {
  Chart,
  LineElement,
  PointElement,
  LineController,
  CategoryScale,
  LinearScale,
  Tooltip,
  Filler
} from 'chart.js'

Chart.register(LineElement, PointElement, LineController, CategoryScale, LinearScale, Tooltip, Filler)

const { t } = useI18n()
const { format } = useCurrency()
const branchStore = useBranchStore()
const { notif } = useAppUtils()

const loading = ref(false)
const activeTab = ref('revenue')
const period = ref('month')
const dateFrom = ref('')
const dateTo = ref('')
const branchId = ref(null)

const summary = ref({ total_revenue: 0, total_orders: 0, avg_order_value: 0 })
const daily = ref([])
const customers = ref([])

const chartRef = ref(null)
let chart = null

const customerHeaders = [
  { title: '#', key: 'rank', sortable: false, width: '48' },
  { title: t('revenue_report.table.customer'), key: 'name', sortable: false },
  { title: t('revenue_report.table.email'), key: 'email', sortable: false },
  { title: t('revenue_report.table.orders'), key: 'order_count', sortable: false },
  { title: t('revenue_report.table.total_spent'), key: 'total_spent', sortable: false },
  { title: t('revenue_report.table.avg_order_value'), key: 'avg_order_value', sortable: false }
]

const customerName = item => {
  const c = item.customer
  const fullName = c?.full_name || [c?.first_name, c?.last_name].filter(Boolean).join(' ')
  return fullName || t('revenue_report.table.unknown_customer')
}

const periodDates = p => {
  const now = new Date()
  const pad = n => String(n).padStart(2, '0')
  const ymd = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
  if (p === 'today') { const d = ymd(now); return { from: d, to: d } }
  if (p === 'week') { const m = new Date(now); m.setDate(now.getDate() - now.getDay() + 1); return { from: ymd(m), to: ymd(now) } }
  if (p === 'month') { return { from: `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`, to: ymd(now) } }
  if (p === 'last_month') {
    const f = new Date(now.getFullYear(), now.getMonth() - 1, 1)
    const l = new Date(now.getFullYear(), now.getMonth(), 0)
    return { from: ymd(f), to: ymd(l) }
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
  loadAll()
}

const onDateChange = ({ from, to }) => {
  dateFrom.value = from
  dateTo.value = to
  period.value = 'custom'
  if (from && to) loadAll()
}

const loadRevenue = async () => {
  const res = await api.get('v1/reports/revenue', {
    params: {
      branch_id: branchId.value ?? undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined
    }
  })
  summary.value = res.data.data.summary
  daily.value = res.data.data.daily
  nextTick(() => drawChart())
}

const loadTopCustomers = async () => {
  const res = await api.get('v1/reports/top-customers', {
    params: {
      branch_id: branchId.value ?? undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined
    }
  })
  customers.value = res.data.data.customers
}

const loadAll = async () => {
  loading.value = true
  try {
    await Promise.all([loadRevenue(), loadTopCustomers()])
  } catch {
    notif(t('revenue_report.load_failed'), { type: 'error' })
  } finally {
    loading.value = false
  }
}

const drawChart = () => {
  if (!chartRef.value) return
  if (chart) chart.destroy()
  chart = new Chart(chartRef.value, {
    type: 'line',
    data: {
      labels: daily.value.map(d => new Date(d.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })),
      datasets: [
        {
          label: t('revenue_report.chart.title'),
          data: daily.value.map(d => parseFloat(d.revenue)),
          borderColor: '#1D9E75',
          backgroundColor: 'rgba(29,158,117,0.12)',
          fill: true,
          tension: 0.4,
          pointRadius: 3
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => format(ctx.parsed.y) } }
      },
      scales: {
        y: { beginAtZero: true, ticks: { callback: v => format(v) } },
        x: { grid: { display: false } }
      }
    }
  })
}

watch(activeTab, val => {
  if (val === 'revenue') nextTick(() => drawChart())
})

onMounted(async () => {
  await branchStore.fetchBranches()
  const d = periodDates(period.value)
  dateFrom.value = d.from
  dateTo.value = d.to
  loadAll()
})
</script>

<style scoped>
.tab-bar-wrap {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-right: 12px;
}
.kpi-card {
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.07) !important;
}
.kpi-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}
.kpi-value {
  font-weight: 900;
  font-size: clamp(0.95rem, 2vw, 1.4rem);
  line-height: 1.2;
}
</style>
