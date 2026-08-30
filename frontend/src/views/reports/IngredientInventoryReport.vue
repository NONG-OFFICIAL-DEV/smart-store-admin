<template>
  <div>
    <custom-title
      icon="mdi-food-variant"
      :title="t('ingredient_inventory_report.title')"
      :subtitle="t('ingredient_inventory_report.subtitle')"
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
          <v-tab value="overview" prepend-icon="mdi-view-dashboard-outline">
            {{ t('ingredient_inventory_report.tabs.overview') }}
          </v-tab>
          <v-tab value="low_stock" prepend-icon="mdi-alert-outline">
            {{ t('ingredient_inventory_report.tabs.low_stock') }}
            <v-chip v-if="report.low_stock?.length" size="x-small" color="warning" variant="tonal" rounded="pill" class="ml-2">
              {{ report.low_stock.length }}
            </v-chip>
          </v-tab>
          <v-tab value="movements" prepend-icon="mdi-swap-vertical">
            {{ t('ingredient_inventory_report.tabs.movements') }}
          </v-tab>
        </v-tabs>
        <v-progress-circular v-if="loading" indeterminate color="primary" size="18" width="2" class="mr-3" />
      </div>

      <v-divider />

      <v-tabs-window v-model="activeTab">
        <!-- ── OVERVIEW ── -->
        <v-tabs-window-item value="overview">
          <div class="pa-4">
            <v-row dense class="mb-4">
              <v-col cols="6" md="4">
                <v-card rounded="xl" border elevation="0" class="kpi-card pa-4">
                  <div class="kpi-header">
                    <span class="text-caption text-medium-emphasis font-weight-medium">
                      {{ t('ingredient_inventory_report.summary.total_ingredients') }}
                    </span>
                    <v-avatar size="30" color="primary" variant="tonal" rounded="lg">
                      <v-icon icon="mdi-food-variant" size="15" />
                    </v-avatar>
                  </div>
                  <div class="kpi-value text-primary">{{ report.summary?.total_ingredients ?? 0 }}</div>
                </v-card>
              </v-col>
              <v-col cols="6" md="4">
                <v-card rounded="xl" border elevation="0" class="kpi-card pa-4">
                  <div class="kpi-header">
                    <span class="text-caption text-medium-emphasis font-weight-medium">
                      {{ t('ingredient_inventory_report.summary.low_stock') }}
                    </span>
                    <v-avatar size="30" color="warning" variant="tonal" rounded="lg">
                      <v-icon icon="mdi-alert-outline" size="15" />
                    </v-avatar>
                  </div>
                  <div class="kpi-value text-warning">{{ report.summary?.low_stock_count ?? 0 }}</div>
                </v-card>
              </v-col>
              <v-col cols="12" md="4">
                <v-card rounded="xl" border elevation="0" class="kpi-card pa-4">
                  <div class="kpi-header">
                    <span class="text-caption text-medium-emphasis font-weight-medium">
                      {{ t('ingredient_inventory_report.summary.out_of_stock') }}
                    </span>
                    <v-avatar size="30" color="error" variant="tonal" rounded="lg">
                      <v-icon icon="mdi-close-circle-outline" size="15" />
                    </v-avatar>
                  </div>
                  <div class="kpi-value text-error">{{ report.summary?.out_of_stock_count ?? 0 }}</div>
                </v-card>
              </v-col>
            </v-row>

            <v-card rounded="xl" border elevation="0" class="pa-4">
              <div class="card-header">
                <div class="title-wrap">
                  <v-icon icon="mdi-alert-circle" color="warning" size="18" />
                  <span class="text-body-2 font-weight-bold">
                    {{ t('ingredient_inventory_report.overview.needs_attention') }}
                  </span>
                  <v-chip size="x-small" color="warning" variant="tonal" rounded="pill">
                    {{ report.low_stock?.length ?? 0 }}
                  </v-chip>
                </div>
                <v-btn
                  v-if="report.low_stock?.length"
                  size="small"
                  variant="tonal"
                  color="primary"
                  rounded="lg"
                  append-icon="mdi-chevron-right"
                  @click="activeTab = 'low_stock'"
                >
                  {{ t('btn.view_all') }}
                </v-btn>
              </div>

              <div v-if="report.low_stock?.length" class="chips-wrap">
                <v-chip
                  v-for="s in report.low_stock.slice(0, 14)"
                  :key="s.id"
                  size="small"
                  :color="s.quantity_on_hand <= 0 ? 'error' : 'warning'"
                  variant="tonal"
                  rounded="lg"
                  :prepend-icon="s.quantity_on_hand <= 0 ? 'mdi-close-circle' : 'mdi-alert'"
                >
                  {{ s.ingredient?.name }}
                  <span class="ml-1 font-weight-black">{{ s.quantity_on_hand }}</span>
                </v-chip>
                <v-chip v-if="report.low_stock.length > 14" size="small" color="grey" variant="tonal" rounded="lg">
                  +{{ report.low_stock.length - 14 }} more
                </v-chip>
              </div>
              <div v-else class="all-good">
                <v-icon icon="mdi-check-circle" size="16" color="success" />
                <span class="text-caption font-weight-medium text-success">
                  {{ t('ingredient_inventory_report.overview.all_stocked') }}
                </span>
              </div>
            </v-card>
          </div>
        </v-tabs-window-item>

        <!-- ── LOW STOCK ── -->
        <v-tabs-window-item value="low_stock">
          <div class="pa-4">
            <v-data-table
              :headers="lowStockHeaders"
              :items="report.low_stock ?? []"
              :loading="loading"
              item-value="id"
            >
              <template #item.name="{ item }">{{ item.ingredient?.name }}</template>
              <template #item.unit="{ item }">{{ item.ingredient?.unit }}</template>
              <template #item.quantity_on_hand="{ item }">
                <v-chip size="small" variant="tonal" :color="item.quantity_on_hand <= 0 ? 'error' : 'warning'" rounded="lg">
                  {{ item.quantity_on_hand }}
                </v-chip>
              </template>
              <template #item.reorder_point="{ item }">{{ item.ingredient?.reorder_point ?? '—' }}</template>
              <template #no-data>
                <div class="d-flex flex-column align-center justify-center pa-10" style="gap: 8px">
                  <v-icon icon="mdi-check-circle-outline" size="44" color="grey-lighten-1" />
                  <div class="text-body-2 text-medium-emphasis">
                    {{ t('ingredient_inventory_report.overview.all_stocked') }}
                  </div>
                </div>
              </template>
            </v-data-table>
          </div>
        </v-tabs-window-item>

        <!-- ── MOVEMENTS ── -->
        <v-tabs-window-item value="movements">
          <div class="pa-4">
            <v-card rounded="xl" border elevation="0" class="pa-5">
              <div class="text-body-1 font-weight-bold mb-4">
                {{ t('ingredient_inventory_report.movement.title') }}
              </div>
              <div v-if="movementEntries.length" class="movement-list">
                <div v-for="m in movementEntries" :key="m.type" class="movement-row">
                  <span class="text-body-2">{{ m.type }}</span>
                  <span class="font-weight-bold">{{ m.quantity }}</span>
                </div>
              </div>
              <div v-else class="d-flex flex-column align-center justify-center pa-10" style="gap: 8px">
                <v-icon icon="mdi-swap-vertical" size="44" color="grey-lighten-1" />
                <div class="text-body-2 text-medium-emphasis">
                  {{ t('ingredient_inventory_report.movement.select_range') }}
                </div>
              </div>
            </v-card>
          </div>
        </v-tabs-window-item>
      </v-tabs-window>
    </v-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBranchStore } from '@/stores/branchStore'
import { useAppUtils } from '@/composables/useAppUtils'
import api from '@/api/api'
import BranchFilterBar from '@/components/common/BranchFilterBar.vue'

const { t } = useI18n()
const branchStore = useBranchStore()
const { notif } = useAppUtils()

const loading = ref(false)
const activeTab = ref('overview')
const period = ref('month')
const dateFrom = ref('')
const dateTo = ref('')
const branchId = ref(null)

const report = ref({
  summary: {},
  stock: [],
  low_stock: [],
  movement_summary: {}
})

const lowStockHeaders = [
  { title: t('ingredient_inventory_report.table.ingredient'), key: 'name', sortable: false },
  { title: t('ingredient_inventory_report.table.unit'), key: 'unit', sortable: false },
  { title: t('ingredient_inventory_report.table.on_hand'), key: 'quantity_on_hand', sortable: false },
  { title: t('ingredient_inventory_report.table.reorder_point'), key: 'reorder_point', sortable: false }
]

const movementEntries = computed(() =>
  Object.entries(report.value.movement_summary ?? {}).map(([type, quantity]) => ({ type, quantity }))
)

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
  load()
}

const onDateChange = ({ from, to }) => {
  dateFrom.value = from
  dateTo.value = to
  period.value = 'custom'
  if (from && to) load()
}

const load = async () => {
  loading.value = true
  try {
    const res = await api.get('v1/reports/inventory', {
      params: {
        branch_id: branchId.value ?? undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined
      }
    })
    report.value = res.data.data
  } catch {
    notif(t('ingredient_inventory_report.load_failed'), { type: 'error' })
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await branchStore.fetchBranches()
  const d = periodDates(period.value)
  dateFrom.value = d.from
  dateTo.value = d.to
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
}
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}
.title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}
.chips-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.all-good {
  display: flex;
  align-items: center;
  gap: 4px;
}
.movement-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.movement-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.03);
}
</style>
