<template>
  <v-container fluid class="pa-0">
    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <div class="d-flex align-center justify-space-between mb-4">
      <custom-title
        icon="mdi-view-dashboard-outline"
        :title="$t('admin_dashboard.title')"
        :subtitle="`${today} · ${$t('admin_dashboard.subtitle_suffix')}`"
      />
      <div class="d-flex align-center gap-3">
        <v-select
          v-model="selectedPeriod"
          :items="periods"
          item-title="title"
          item-value="value"
          variant="outlined"
          hide-details
          rounded="lg"
          style="width: 130px"
          @update:model-value="onPeriodChange"
        />
        <v-btn
          variant="tonal"
          rounded="lg"
          prepend-icon="mdi-refresh"
          :loading="isLoading"
          @click="onPeriodChange"
        >
          {{ $t('btn.refresh') }}
        </v-btn>
      </div>
    </div>

    <!-- ── KPI Row — 5 cards, equal width, no trailing gap ───────────────── -->
    <div class="kpi-grid mb-5">
      <template v-if="isLoading">
        <v-skeleton-loader
          v-for="i in 5"
          :key="i"
          type="article"
          rounded="lg"
        />
      </template>
      <template v-else>
        <v-card
          v-for="(kpi, i) in kpis"
          :key="kpi.key"
          rounded="xl"
          elevation="0"
          border
          :class="['kpi-card', i === 0 ? 'kpi-card--hero' : '']"
          :color="i === 0 ? 'primary' : undefined"
        >
          <v-card-text class="pa-4">
            <div class="d-flex align-center justify-space-between mb-3">
              <v-avatar
                :color="i === 0 ? 'rgba(255,255,255,0.2)' : kpi.color"
                size="36"
                rounded="lg"
              >
                <v-icon :icon="kpi.icon" size="18" color="white" />
              </v-avatar>
              <v-chip
                v-if="kpi.trend !== 0"
                :color="trendColor(kpi, i)"
                variant="tonal"
                size="x-small"
                rounded="lg"
              >
                <v-icon
                  :icon="
                    kpi.trend > 0 ? 'mdi-trending-up' : 'mdi-trending-down'
                  "
                  size="11"
                  class="mr-1"
                />
                {{ Math.abs(kpi.trend) }}%
              </v-chip>
            </div>
            <div
              :class="[
                'text-h6 font-weight-black',
                i === 0 ? 'text-white' : ''
              ]"
            >
              {{ kpi.value }}
            </div>
            <div
              :class="[
                'text-caption mt-1',
                i === 0 ? 'text-white opacity-80' : 'text-medium-emphasis'
              ]"
            >
              {{ kpi.label }}
            </div>
            <div
              v-if="kpi.trend_label"
              :class="[
                'text-caption',
                i === 0 ? 'text-white opacity-60' : 'text-disabled'
              ]"
            >
              {{ kpi.trend_label }}
            </div>
          </v-card-text>
        </v-card>
      </template>
    </div>

    <!-- ── Main row ───────────────────────────────────────────────────────── -->
    <v-row dense>
      <!-- Left: MRR chart + top tenants -->
      <v-col cols="12" lg="8">
        <!-- MRR chart -->
        <v-card rounded="xl" elevation="0" border class="mb-4">
          <v-card-title class="pa-5 pb-3">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-subtitle-1 font-weight-bold">
                  {{ $t('admin_dashboard.mrr_trend') }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ $t('admin_dashboard.mrr_trend_subtitle') }}
                </div>
              </div>
              <v-btn-toggle
                v-model="chartMode"
                mandatory
                density="compact"
                rounded="lg"
                variant="outlined"
                color="primary"
              >
                <v-btn value="mrr" size="small">
                  {{ $t('admin_dashboard.mrr_label') }}
                </v-btn>
                <v-btn value="subscriptions" size="small">
                  {{ $t('menu.subscriptions') }}
                </v-btn>
              </v-btn-toggle>
            </div>
          </v-card-title>
          <v-card-text class="pa-5 pt-2">
            <v-skeleton-loader
              v-if="store.loading.chart"
              type="image"
              height="180"
            />
            <div v-else class="chart-wrap">
              <canvas ref="chartCanvas" height="180" />
            </div>
          </v-card-text>
        </v-card>

        <!-- Tenants — top by MRR / recently joined, tabbed instead of two
             separate cards (they're both just "a list of tenants") -->
        <v-card rounded="xl" elevation="0" border>
          <v-card-title class="pa-5 pb-3">
            <div class="d-flex align-center justify-space-between">
              <v-tabs
                v-model="activeTenantTab"
                density="compact"
                color="primary"
              >
                <v-tab value="top">
                  {{ $t('admin_dashboard.top_tenants_by_mrr') }}
                </v-tab>
                <v-tab value="recent">
                  {{ $t('admin_dashboard.recent_tenants') }}
                </v-tab>
              </v-tabs>
              <v-btn
                variant="text"
                color="primary"
                size="small"
                append-icon="mdi-arrow-right"
                @click="$router.push('/tenants')"
              >
                {{ $t('admin_dashboard.manage_tenants') }}
              </v-btn>
            </div>
          </v-card-title>
          <v-divider />
          <v-list v-if="activeTenantTab === 'top'" class="pa-2">
            <template v-if="isLoading">
              <v-skeleton-loader
                v-for="n in 4"
                :key="n"
                type="list-item-two-line"
                class="mb-1"
              />
            </template>
            <template v-else>
              <v-list-item
                v-for="(tenant, idx) in topTenants"
                :key="tenant.id"
                rounded="lg"
                class="mb-1 tenant-row"
                @click="$router.push(`/tenants/${tenant.id}`)"
              >
                <template #prepend>
                  <v-avatar
                    :color="rowColor(idx)"
                    size="40"
                    rounded="lg"
                    class="mr-3"
                  >
                    <img
                      v-if="tenant.logo_url"
                      :src="tenant.logo_url"
                      style="width: 100%; height: 100%; object-fit: cover"
                    />
                    <v-icon v-else icon="mdi-domain" size="20" color="white" />
                  </v-avatar>
                </template>

                <v-list-item-title
                  class="font-weight-medium text-body-2 d-flex align-center flex-wrap gap-1"
                >
                  {{ tenant.name }}
                  <v-chip
                    v-if="tenant.rank === 1"
                    size="x-small"
                    color="warning"
                    variant="tonal"
                  >
                    <v-icon icon="mdi-crown" size="10" class="mr-1" />
                    {{ $t('dashboard.top') }}
                  </v-chip>
                  <v-chip
                    :color="subscriptionStatusColor(tenant.subscription_status)"
                    size="x-small"
                    variant="tonal"
                  >
                    {{ tenant.subscription_status }}
                  </v-chip>
                </v-list-item-title>

                <v-list-item-subtitle class="text-caption">
                  <v-icon icon="mdi-tag-outline" size="12" class="mr-1" />
                  {{ tenant.plan_name }}
                  <template v-if="tenant.billing_label">
                    · {{ tenant.billing_label }}
                  </template>
                  <template v-if="tenant.discount_percent > 0">
                    <v-chip
                      size="x-small"
                      color="success"
                      variant="tonal"
                      class="ml-1"
                    >
                      {{
                        $t('admin_dashboard.percent_off', {
                          percent: tenant.discount_percent
                        })
                      }}
                    </v-chip>
                  </template>
                  <template
                    v-if="
                      tenant.subscription_status === 'trial' &&
                      tenant.trial_ends_at
                    "
                  >
                    ·
                    {{
                      $t('admin_dashboard.trial_ends', {
                        date: tenant.trial_ends_at
                      })
                    }}
                  </template>
                  <template v-else-if="tenant.period_end">
                    ·
                    {{
                      $t('admin_dashboard.renews', { date: tenant.period_end })
                    }}
                  </template>
                </v-list-item-subtitle>

                <template #append>
                  <div class="d-flex align-center gap-4">
                    <div style="width: 100px">
                      <div class="d-flex justify-space-between mb-1">
                        <span class="text-caption text-medium-emphasis">
                          {{ tenant.revenue_percent }}%
                        </span>
                      </div>
                      <v-progress-linear
                        :model-value="tenant.revenue_percent"
                        :color="rowColor(idx)"
                        rounded
                        height="6"
                        bg-color="grey-lighten-3"
                      />
                    </div>
                    <div class="text-right" style="min-width: 80px">
                      <div class="font-weight-bold text-body-2">
                        ${{ tenant.monthly_value?.toLocaleString() }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{ $t('subscription.month_short') }}
                      </div>
                    </div>
                  </div>
                </template>
              </v-list-item>
              <div
                v-if="!topTenants.length"
                class="text-center py-6 text-medium-emphasis text-body-2"
              >
                {{ $t('admin_dashboard.no_tenants_found') }}
              </div>
            </template>
          </v-list>

          <v-list v-else class="pa-2">
            <v-skeleton-loader
              v-if="isLoading"
              type="list-item-two-line"
              class="mb-1"
            />
            <template v-else>
              <v-list-item
                v-for="(tenant, idx) in recentTenants"
                :key="tenant.id"
                rounded="lg"
                class="mb-1 tenant-row"
                @click="$router.push(`/tenants/${tenant.id}`)"
              >
                <template #prepend>
                  <v-avatar
                    :color="rowColor(idx)"
                    size="34"
                    rounded="lg"
                    class="mr-2"
                  >
                    <img
                      v-if="tenant.logo_url"
                      :src="tenant.logo_url"
                      style="width: 100%; height: 100%; object-fit: cover"
                    />
                    <v-icon v-else icon="mdi-domain" size="18" color="white" />
                  </v-avatar>
                </template>
                <v-list-item-title class="text-body-2 font-weight-medium">
                  {{ tenant.name }}
                </v-list-item-title>
                <v-list-item-subtitle class="text-caption">
                  {{ tenant.plan_name }} ·
                  <v-chip
                    :color="subscriptionStatusColor(tenant.status)"
                    size="x-small"
                    variant="tonal"
                  >
                    {{ tenant.status }}
                  </v-chip>
                </v-list-item-subtitle>
                <template #append>
                  <span class="text-caption text-disabled">
                    {{ tenant.created_at }}
                  </span>
                </template>
              </v-list-item>
              <div
                v-if="!recentTenants.length"
                class="text-center py-4 text-caption text-medium-emphasis"
              >
                {{ $t('admin_dashboard.no_recent_tenants') }}
              </div>
            </template>
          </v-list>
        </v-card>
      </v-col>

      <!-- Right: Billing & Plans -->
      <v-col cols="12" lg="4">
        <!-- Billing & Plans card -->
        <v-card rounded="xl" elevation="0" border class="mb-4">
          <v-card-title class="pa-5 pb-3">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-subtitle-1 font-weight-bold">
                  {{ $t('admin_dashboard.billing_plans') }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ $t('admin_dashboard.subscription_breakdown') }}
                </div>
              </div>
              <v-chip
                v-if="billing.overdue_count > 0"
                color="error"
                size="small"
                variant="tonal"
                prepend-icon="mdi-alert-circle-outline"
              >
                {{
                  $t('admin_dashboard.overdue_count', {
                    count: billing.overdue_count
                  })
                }}
              </v-chip>
            </div>
          </v-card-title>

          <v-card-text class="pa-5 pt-0">
            <v-skeleton-loader v-if="isLoading" type="list-item-three-line" />
            <template v-else>
              <!-- Status summary chips -->
              <div class="d-flex flex-wrap gap-2 mb-4">
                <v-chip
                  size="small"
                  color="success"
                  variant="tonal"
                  prepend-icon="mdi-check-circle-outline"
                >
                  {{ billing.total_active }} {{ $t('status.active') }}
                </v-chip>
                <v-chip
                  size="small"
                  color="warning"
                  variant="tonal"
                  prepend-icon="mdi-clock-outline"
                >
                  {{ billing.total_trial }} {{ $t('status.trial') }}
                </v-chip>
                <v-chip
                  size="small"
                  color="error"
                  variant="tonal"
                  prepend-icon="mdi-pause-circle-outline"
                >
                  {{ billing.total_suspended }} {{ $t('status.suspended') }}
                </v-chip>
                <v-chip
                  size="small"
                  color="secondary"
                  variant="tonal"
                  prepend-icon="mdi-cancel"
                >
                  {{ billing.total_cancelled }} {{ $t('status.cancelled') }}
                </v-chip>
              </div>

              <!-- Renewals alert -->
              <v-alert
                v-if="billing.renewals_soon > 0"
                type="info"
                variant="tonal"
                density="compact"
                rounded="lg"
                class="mb-4"
                icon="mdi-calendar-clock"
              >
                <span class="text-caption">
                  <strong>{{ billing.renewals_soon }}</strong>
                  {{ $t('admin_dashboard.renewals_due_suffix') }}
                </span>
              </v-alert>

              <!-- Per-plan breakdown -->
              <div
                v-for="(plan, i) in billing.plans"
                :key="plan.plan_id"
                class="mb-4"
              >
                <div class="d-flex align-center justify-space-between mb-1">
                  <div class="d-flex align-center gap-2">
                    <v-avatar :color="rowColor(i)" size="24" rounded="sm">
                      <v-icon icon="mdi-tag-outline" size="13" color="white" />
                    </v-avatar>
                    <span class="text-body-2 font-weight-medium">
                      {{ plan.plan_name }}
                    </span>
                    <span class="text-caption text-medium-emphasis">
                      ${{ plan.price_usd }}{{ $t('subscription.month_short') }}
                    </span>
                  </div>
                  <span class="text-body-2 font-weight-bold text-success">
                    ${{ plan.mrr_contribution?.toLocaleString() }}
                  </span>
                </div>
                <v-progress-linear
                  :model-value="planPercent(plan)"
                  :color="rowColor(i)"
                  rounded
                  height="5"
                  bg-color="grey-lighten-3"
                />
              </div>
              <div
                v-if="!billing.plans?.length"
                class="text-center py-4 text-caption text-medium-emphasis"
              >
                {{ $t('admin_dashboard.no_plan_data') }}
              </div>
            </template>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
  import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAdminDashboardStore } from '@/stores/adminDashboardStore'
  const { t } = useI18n()

  import {
    Chart,
    LineController,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Filler,
    Tooltip
  } from 'chart.js'

  Chart.register(
    LineController,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Filler,
    Tooltip
  )

  // ── Store ──────────────────────────────────────────────────────────────────────
  const store = useAdminDashboardStore()

  // ── Period & chart mode ────────────────────────────────────────────────────────
  const periodValues = ['Today', 'Week', 'Month', 'Year']
  const periods = computed(() =>
    periodValues.map(p => ({
      title: t(`dashboard.periods.${p.toLowerCase()}`),
      value: p
    }))
  )
  const selectedPeriod = ref('Month')
  const chartMode = ref('mrr')
  const activeTenantTab = ref('top')

  const today = new Date().toLocaleDateString('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })

  // ── Derived data ───────────────────────────────────────────────────────────────
  const isLoading = computed(() => store.loading.stats || store.loading.chart)
  const kpis = computed(() => store.stats?.kpis ?? [])
  const topTenants = computed(() => store.stats?.top_tenants ?? [])
  const recentTenants = computed(() => store.stats?.recent_tenants ?? [])
  const billing = computed(
    () =>
      store.stats?.billing ?? {
        plans: [],
        total_active: 0,
        total_trial: 0,
        total_suspended: 0,
        total_cancelled: 0,
        overdue_count: 0,
        renewals_soon: 0
      }
  )

  // Max MRR across plans (for progress bars in billing panel)
  const maxPlanMrr = computed(() =>
    Math.max(...(billing.value.plans ?? []).map(p => p.mrr_contribution), 1)
  )
  const planPercent = plan =>
    Math.round((plan.mrr_contribution / maxPlanMrr.value) * 100)

  // ── Colors ─────────────────────────────────────────────────────────────────────
  const rowColors = [
    'primary',
    'success',
    'warning',
    'secondary',
    'info',
    'error',
    'teal',
    'purple'
  ]
  const rowColor = i => rowColors[i % rowColors.length]

  const subscriptionStatusColor = status =>
    ({
      active: 'success',
      trial: 'warning',
      suspended: 'error',
      cancelled: 'secondary'
    })[status] ?? 'secondary'

  /**
   * Trend chip color.
   * For churn (trend_inverted = true): up trend = red (bad), down = green (good).
   * For everything else: up = green, down = red.
   */
  const trendColor = (kpi, i) => {
    const isHero = i === 0
    const isUp = kpi.trend > 0
    const isBad = kpi.trend_inverted ? isUp : !isUp
    if (isHero) return isUp ? 'rgba(255,255,255,0.25)' : 'rgba(255,100,100,0.3)'
    return isBad ? 'error' : 'success'
  }

  // ── Chart ──────────────────────────────────────────────────────────────────────
  const chartCanvas = ref(null)
  let chartInstance = null

  const buildChart = () => {
    if (!chartCanvas.value) return
    if (chartInstance) chartInstance.destroy()

    const labels = store.chart.map(r => r.label)
    const values = store.chart.map(r =>
      chartMode.value === 'mrr' ? r.mrr : r.subscriptions
    )

    chartInstance = new Chart(chartCanvas.value, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            data: values,
            borderColor: '#1867C0',
            borderWidth: 2.5,
            pointBackgroundColor: '#fff',
            pointBorderColor: '#1867C0',
            pointBorderWidth: 2,
            pointRadius: 4,
            tension: 0.3,
            fill: true,
            backgroundColor: ctx => {
              const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 180)
              gradient.addColorStop(0, 'rgba(24,103,192,0.25)')
              gradient.addColorStop(1, 'rgba(24,103,192,0)')
              return gradient
            }
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: ctx =>
                chartMode.value === 'mrr'
                  ? `$${ctx.parsed.y.toLocaleString()}/mo`
                  : `${ctx.parsed.y} subscriptions`
            }
          }
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { color: '#9e9e9e', font: { size: 11 } }
          },
          y: {
            grid: { color: 'rgba(0,0,0,0.06)' },
            ticks: {
              color: '#9e9e9e',
              font: { size: 11 },
              callback: v =>
                chartMode.value === 'mrr' ? `$${v.toLocaleString()}` : v
            }
          }
        }
      }
    })
  }

  watch([() => store.chart, chartMode], async () => {
    await nextTick()
    buildChart()
  })

  // ── Lifecycle ──────────────────────────────────────────────────────────────────
  const onPeriodChange = () =>
    store.fetchAll(selectedPeriod.value.toLowerCase())

  onMounted(async () => {
    await store.fetchAll(selectedPeriod.value.toLowerCase())
    await nextTick()
    buildChart()
  })

  onUnmounted(() => {
    if (chartInstance) chartInstance.destroy()
  })
</script>

<style scoped>
  /* ── KPI grid: always 5 equal columns, no trailing gap ── */
  .kpi-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 8px;
  }

  /* Responsive: collapse to 2–3 columns on smaller screens */
  @media (max-width: 1279px) {
    .kpi-grid {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }
  @media (max-width: 599px) {
    .kpi-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  /* ── Hero KPI card ── */
  .kpi-card--hero {
    background: linear-gradient(135deg, #1867c0 0%, #1565c0 100%) !important;
    box-shadow: 0 8px 24px rgba(24, 103, 192, 0.3) !important;
  }

  /* ── Tenant list rows ── */
  .tenant-row {
    transition: background 0.15s;
    cursor: pointer;
  }

  /* ── Chart container ── */
  .chart-wrap {
    position: relative;
    height: 180px;
  }

  /* ── Opacity helpers ── */
  .opacity-80 {
    opacity: 0.8;
  }
  .opacity-60 {
    opacity: 0.6;
  }

  /* ── Letter spacing ── */
  .tracking-wide {
    letter-spacing: 0.08em;
  }

  /* ── Gap utilities ── */
  .gap-1 {
    gap: 4px;
  }
  .gap-2 {
    gap: 8px;
  }
  .gap-3 {
    gap: 12px;
  }
  .gap-4 {
    gap: 16px;
  }
</style>
