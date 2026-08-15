<template>
  <div>
    <custom-title
      icon="mdi-warehouse"
      :title="t('inventory_report.title')"
      :subtitle="t('inventory_report.subtitle')"
    >
      <template #right>
        <v-btn variant="outlined" color="success" prepend-icon="mdi-file-excel">
          {{ t('btn.export') }} Excel
        </v-btn>
      </template>
    </custom-title>

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
      <!-- Tab bar -->
      <div class="tab-bar-wrap">
        <v-tabs v-model="activeTab" color="primary" density="comfortable">
          <v-tab value="overview" prepend-icon="mdi-view-dashboard-outline">
            {{ t('inventory_report.tabs.overview', 'Overview') }}
          </v-tab>
          <v-tab value="movements" prepend-icon="mdi-swap-vertical">
            {{ t('inventory_report.tabs.movements', 'Movements') }}
            <v-chip v-if="report.movement_summary?.length" size="x-small" color="primary" variant="tonal" rounded="pill" class="ml-2">
              {{ report.movement_summary.length }}
            </v-chip>
          </v-tab>
          <v-tab value="products" prepend-icon="mdi-package-variant">
            {{ t('inventory_report.tabs.all', 'All Products') }}
            <v-chip v-if="filteredProducts.length" size="x-small" color="primary" variant="tonal" rounded="pill" class="ml-2">
              {{ filteredProducts.length }}
            </v-chip>
          </v-tab>
        </v-tabs>
        <v-progress-circular v-if="loading" indeterminate color="primary" size="18" width="2" class="mr-3" />
      </div>

      <v-divider />

      <v-tabs-window v-model="activeTab">

        <!-- ── OVERVIEW ── -->
        <v-tabs-window-item value="overview">
          <div class="pa-4">
            <!-- KPIs -->
            <v-row dense class="mb-4">
              <v-col cols="6" md="3">
                <v-card rounded="xl" border elevation="0" class="kpi-card pa-4">
                  <div class="kpi-header">
                    <span class="text-caption text-medium-emphasis font-weight-medium">
                      {{ t('inventory_report.summary.total_products') }}
                    </span>
                    <v-avatar size="30" color="primary" variant="tonal" rounded="lg">
                      <v-icon icon="mdi-package-variant" size="15" />
                    </v-avatar>
                  </div>
                  <div class="text-h4 font-weight-black text-primary mb-2">
                    {{ report.summary?.total_products ?? 0 }}
                  </div>
                  <div class="d-flex flex-wrap gap-1">
                    <v-chip size="x-small" color="success" variant="tonal" rounded="lg">{{ report.summary?.in_stock ?? 0 }} {{ t('inventory_report.status.in_stock') }}</v-chip>
                    <v-chip size="x-small" color="warning" variant="tonal" rounded="lg">{{ report.summary?.low_stock ?? 0 }} {{ t('inventory_report.status.low_stock') }}</v-chip>
                    <v-chip size="x-small" color="error"   variant="tonal" rounded="lg">{{ report.summary?.out_of_stock ?? 0 }} {{ t('inventory_report.status.out_of_stock') }}</v-chip>
                  </div>
                </v-card>
              </v-col>

              <v-col cols="6" md="3">
                <v-card rounded="xl" border elevation="0" class="kpi-card pa-4">
                  <div class="kpi-header">
                    <span class="text-caption text-medium-emphasis font-weight-medium">{{ t('inventory_report.summary.cost_value') }}</span>
                    <v-avatar size="30" color="indigo" variant="tonal" rounded="lg"><v-icon icon="mdi-cash-multiple" size="15" /></v-avatar>
                  </div>
                  <div class="kpi-value text-indigo mb-1">{{ format(report.summary?.total_cost_value) }}</div>
                  <div class="text-caption text-medium-emphasis">{{ t('inventory_report.summary.at_cost_price') }}</div>
                </v-card>
              </v-col>

              <v-col cols="6" md="3">
                <v-card rounded="xl" border elevation="0" class="kpi-card pa-4">
                  <div class="kpi-header">
                    <span class="text-caption text-medium-emphasis font-weight-medium">{{ t('inventory_report.summary.retail_value') }}</span>
                    <v-avatar size="30" color="teal" variant="tonal" rounded="lg"><v-icon icon="mdi-tag-outline" size="15" /></v-avatar>
                  </div>
                  <div class="kpi-value text-teal mb-1">{{ format(report.summary?.total_retail_value) }}</div>
                  <div class="text-caption text-medium-emphasis">{{ t('inventory_report.summary.at_selling_price') }}</div>
                </v-card>
              </v-col>

              <v-col cols="6" md="3">
                <v-card rounded="xl" border elevation="0" class="kpi-card pa-4">
                  <div class="kpi-header">
                    <span class="text-caption text-medium-emphasis font-weight-medium">{{ t('inventory_report.summary.potential_profit') }}</span>
                    <v-avatar size="30" color="success" variant="tonal" rounded="lg"><v-icon icon="mdi-trending-up" size="15" /></v-avatar>
                  </div>
                  <div class="kpi-value text-success mb-1">{{ format(report.summary?.potential_profit) }}</div>
                  <div class="text-caption text-medium-emphasis">{{ t('inventory_report.summary.if_all_sold') }}</div>
                </v-card>
              </v-col>
            </v-row>

            <!-- Stock Health + Category -->
            <v-row dense class="mb-4">
              <v-col cols="12" md="5">
                <StockHealthCard
                  :in-stock="report.summary?.in_stock ?? 0"
                  :low-stock="report.summary?.low_stock ?? 0"
                  :out-of-stock="report.summary?.out_of_stock ?? 0"
                />
              </v-col>
              <v-col cols="12" md="7">
                <CategoryBreakdownCard :categories="report.by_category ?? []" />
              </v-col>
            </v-row>

            <!-- Needs Attention -->
            <NeedsAttentionCard
              :products="alertProducts"
              @view-all="goToAlerts"
            />
          </div>
        </v-tabs-window-item>

        <!-- ── MOVEMENTS ── -->
        <v-tabs-window-item value="movements">
          <MovementSummaryTab
            :movements="report.movement_summary ?? []"
            :product-movements="report.product_movements ?? []"
            :date-from="dateFrom"
            :date-to="dateTo"
          />
        </v-tabs-window-item>

        <!-- ── PRODUCTS ── -->
        <v-tabs-window-item value="products">
          <ProductsTab
            :products="filteredProducts"
            :search="search"
            :stock-filter="stockFilter"
            :loading="loading"
            @update:search="onSearchUpdate"
            @update:stock-filter="stockFilter = $event"
          />
        </v-tabs-window-item>

      </v-tabs-window>
    </v-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/authStore'
import { useBranchStore } from '@/stores/branchStore'
import { useAppUtils } from '@/composables/useAppUtils'
import { useCurrency } from '@/composables/useCurrency_v2.js'
import api from '@/api/api'

import BranchFilterBar       from '@/components/common/BranchFilterBar.vue'
import StockHealthCard       from '@/components/reports/StockHealthCard.vue'
import CategoryBreakdownCard from '@/components/reports/CategoryBreakdownCard.vue'
import NeedsAttentionCard    from '@/components/reports/NeedsAttentionCard.vue'
import MovementSummaryTab    from '@/components/reports/MovementSummaryTab.vue'
import ProductsTab           from '@/components/reports/ProductsTab.vue'

const { t }      = useI18n()
const { format } = useCurrency()
const authStore  = useAuthStore()
const branchStore = useBranchStore()
const { notif }  = useAppUtils()

const loading     = ref(false)
const period      = ref('month')
const dateFrom    = ref('')
const dateTo      = ref('')
const branchId    = ref(null)
const search      = ref('')
const stockFilter = ref(null)
const activeTab   = ref('overview')

const report = ref({
  summary: {},
  products: [],
  by_category: [],
  movement_summary: [],
  product_movements: []
})

// ── Derived ───────────────────────────────────────────────────────────────────
const filteredProducts = computed(() => {
  let list = report.value.products ?? []
  if (stockFilter.value) list = list.filter(p => p.stock_status === stockFilter.value)
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(p => p.name.toLowerCase().includes(q) || (p.sku ?? '').toLowerCase().includes(q))
  }
  return list
})

const alertProducts = computed(() =>
  (report.value.products ?? []).filter(
    p => p.stock_status === 'low_stock' || p.stock_status === 'out_of_stock'
  )
)

// ── Actions ───────────────────────────────────────────────────────────────────
const goToAlerts = () => {
  activeTab.value   = 'products'
  stockFilter.value = 'out_of_stock'
}

const onSearchUpdate = val => {
  search.value = val ?? ''
  clearTimeout(searchTimer)
  searchTimer = setTimeout(load, 500)
}

let searchTimer = null

// ── Period ────────────────────────────────────────────────────────────────────
const periodDates = p => {
  const now = new Date()
  const pad = n => String(n).padStart(2, '0')
  const ymd = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
  if (p === 'today')      { const d = ymd(now); return { from: d, to: d } }
  if (p === 'week')       { const m = new Date(now); m.setDate(now.getDate() - now.getDay() + 1); return { from: ymd(m), to: ymd(now) } }
  if (p === 'month')      { return { from: `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`, to: ymd(now) } }
  if (p === 'year')       { return { from: `${now.getFullYear()}-01-01`, to: ymd(now) } }
  return { from: '', to: '' }
}

const onPeriodChange = p => {
  period.value = p
  if (p !== 'custom') {
    const d = periodDates(p)
    dateFrom.value = d.from
    dateTo.value   = d.to
  }
  load()
}

const onDateChange = ({ from, to }) => {
  dateFrom.value = from
  dateTo.value   = to
  period.value   = 'custom'
  if (from && to) load()
}

// ── Load ──────────────────────────────────────────────────────────────────────
const load = async () => {
  loading.value = true
  try {
    const res = await api.get('v1/mart/reports/inventory', {
      params: {
        branch_id: branchId.value ?? authStore.branch_id ?? undefined,
        date_from: dateFrom.value || undefined,
        date_to:   dateTo.value   || undefined,
        search:    search.value   || undefined
      }
    })
    report.value = res.data.data
  } catch {
    notif(t('inventory_report.load_failed'), { type: 'error' })
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await branchStore.fetchBranches()
  const d = periodDates(period.value)
  dateFrom.value = d.from
  dateTo.value   = d.to
  load()
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
  word-break: break-all;
}

.gap-1 { gap: 4px; }
</style>