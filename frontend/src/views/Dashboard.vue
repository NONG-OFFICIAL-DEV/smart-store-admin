<template>
  <v-container fluid class="pa-0 dashboard">
    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <div class="d-flex align-center justify-space-between mb-0">
      <custom-title
        icon="mdi-view-dashboard-outline"
        :title="`Good ${greeting}, ${userName}`"
        :subtitle="`${today} · ${$t('dashboard.branches_network', { count: branches.length })}`"
      ></custom-title>
      <div class="d-flex align-center gap-3">
        <v-select
          v-model="selectedPeriod"
          :items="periods"
          item-title="label"
          item-value="key"
          variant="outlined"
          hide-details
          rounded="lg"
          @update:model-value="onPeriodChange"
        />
      </div>
    </div>

    <!-- ── KPI Row ────────────────────────────────────────────────────────── -->
    <v-row dense class="mb-5">
      <v-col v-for="(kpi, i) in kpis" :key="kpi.label" cols="6" sm="3">
        <v-card
          rounded="xl"
          elevation="0"
          border
          :class="['kpi-card', i === 0 ? 'kpi-card--hero' : '']"
          :color="i === 0 ? 'primary' : undefined"
        >
          <v-card-text class="pa-5">
            <v-skeleton-loader v-if="store.loading.stats" type="article" />
            <template v-else>
              <div class="d-flex align-center justify-space-between mb-3">
                <v-avatar
                  :color="i === 0 ? 'rgba(255,255,255,0.2)' : kpi.color"
                  size="40"
                  rounded="lg"
                >
                  <v-icon :icon="kpi.icon" size="20" color="white" />
                </v-avatar>
                <v-chip
                  :color="
                    kpi.trend > 0
                      ? i === 0
                        ? 'rgba(255,255,255,0.25)'
                        : 'success'
                      : 'error'
                  "
                  variant="tonal"
                  size="x-small"
                  rounded="lg"
                >
                  <v-icon
                    :icon="
                      kpi.trend > 0 ? 'mdi-trending-up' : 'mdi-trending-down'
                    "
                    size="12"
                    class="mr-1"
                  />
                  {{ Math.abs(kpi.trend) }}%
                </v-chip>
              </div>
              <div
                :class="[
                  'text-h5 font-weight-black',
                  i === 0 ? 'text-white' : ''
                ]"
              >
                {{ kpi.isCurrency ? format(kpi.value) : kpi.value }}
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
                :class="[
                  'text-caption mt-1',
                  i === 0 ? 'text-white opacity-60' : 'text-disabled'
                ]"
              >
                {{
                  $t('dashboard.vs_last', {
                    period: $t(`dashboard.comparison_periods.${selectedPeriod}`)
                  })
                }}
              </div>
            </template>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row dense>
      <!-- ── Revenue Chart ────────────────────────────────────────────────── -->
      <v-col cols="12" lg="8">
        <RevenueChart
          :chart-data="store.chart"
          :loading="store.loading.chart"
        />
        <!-- ── Branch Performance ──────────────────────────────────────────── -->
        <v-card rounded="xl" elevation="0" border>
          <v-card-title class="pa-5 pb-3">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-subtitle-1 font-weight-bold">
                  {{ $t('dashboard.branch_performance') }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ $t('dashboard.branch_performance_subtitle') }}
                </div>
              </div>
              <v-btn
                variant="text"
                color="primary"
                size="small"
                append-icon="mdi-arrow-right"
                @click="$router.push('/branches')"
              >
                {{ $t('btn.view_all') }}
              </v-btn>
            </div>
          </v-card-title>
          <v-divider />
          <v-list class="pa-2">
            <v-skeleton-loader
              v-if="store.loading.stats"
              v-for="n in 3"
              :key="n"
              type="list-item-two-line"
              class="mb-1"
            />
            <template v-else>
              <v-list-item
                v-for="(branch, idx) in branches"
                :key="branch.id"
                rounded="lg"
                class="mb-1 branch-row"
                @click="$router.push(`/branches/${branch.id}`)"
              >
                <template #prepend>
                  <v-avatar
                    :color="branchColor(idx)"
                    size="38"
                    rounded="lg"
                    class="mr-3"
                  >
                    <v-icon icon="mdi-store" size="18" color="white" />
                  </v-avatar>
                </template>
                <v-list-item-title class="font-weight-medium text-body-2">
                  {{ branch.name }}
                  <v-chip
                    v-if="branch.rank === 1"
                    size="x-small"
                    color="warning"
                    variant="tonal"
                    class="ml-2"
                  >
                    <v-icon icon="mdi-crown" size="10" class="mr-1" />
                    {{ $t('dashboard.top') }}
                  </v-chip>
                </v-list-item-title>
                <v-list-item-subtitle class="text-caption">
                  {{ branch.city }} ·
                  {{ t('dashboard.orders_today', { count: branch.orders }) }}
                </v-list-item-subtitle>
                <template #append>
                  <div class="d-flex align-center gap-4">
                    <div style="width: 100px">
                      <div class="d-flex justify-space-between mb-1">
                        <span class="text-caption text-medium-emphasis">
                          {{ branch.revenuePercent }}%
                        </span>
                      </div>
                      <v-progress-linear
                        :model-value="branch.revenuePercent"
                        :color="branchColor(idx)"
                        rounded
                        height="6"
                        bg-color="grey-lighten-3"
                      />
                    </div>
                    <div class="text-right" style="min-width: 80px">
                      <div class="font-weight-bold text-body-2">
                        ${{ branch.revenue?.toLocaleString() }}
                      </div>
                      <div
                        class="text-caption"
                        :class="
                          branch.growth >= 0 ? 'text-success' : 'text-error'
                        "
                      >
                        <v-icon
                          :icon="
                            branch.growth >= 0
                              ? 'mdi-trending-up'
                              : 'mdi-trending-down'
                          "
                          size="11"
                        />
                        {{ Math.abs(branch.growth) }}%
                      </div>
                    </div>
                  </div>
                </template>
              </v-list-item>
              <div
                v-if="!branches.length"
                class="text-center py-6 text-medium-emphasis text-body-2"
              >
                {{ $t('dashboard.no_branches') }}
              </div>
            </template>
          </v-list>
        </v-card>
      </v-col>
      <v-col cols="12" lg="4">
        <v-card rounded="xl" elevation="0" border>
          <v-card-title class="pa-5 pb-2">
            <div class="text-subtitle-1 font-weight-bold">
              {{ $t('dashboard.quick_actions') }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ $t('dashboard.quick_actions_subtitle') }}
            </div>
          </v-card-title>

          <v-card-text class="pa-3 pt-0">
            <v-list lines="one" class="bg-transparent">
              <v-list-item
                v-for="action in quickActions"
                :key="action.label"
                :prepend-icon="action.icon"
                :title="action.label"
                :color="action.color"
                class="mb-1 rounded-lg text-body-2 font-weight-semibold"
                link
                @click="$router.push(action.route)"
              >
                <template v-slot:prepend>
                  <v-avatar
                    :color="action.color"
                    variant="tonal"
                    size="36"
                    class="mr-3"
                  >
                    <v-icon :icon="action.icon" size="20" />
                  </v-avatar>
                </template>
                <template v-slot:append>
                  <v-icon
                    icon="mdi-chevron-right"
                    size="18"
                    class="text-medium-emphasis"
                  />
                </template>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useDashboardStore } from '@/stores/dashboardStore'
  import { useAuthStore } from '@/stores/authStore'
  import RevenueChart from '@/components/dashboard/RevenueChart.vue'
  const store = useDashboardStore()
  const authStore = useAuthStore()
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import { useI18n } from 'vue-i18n'
  const { format } = useCurrency()
  const { t } = useI18n()
  // ── Period ─────────────────────────────────────────────────────────────────────
  const periods = computed(() => [
    {
      key: 'today',
      label: t('dashboard.periods.today')
    },
    {
      key: 'week',
      label: t('dashboard.periods.week')
    },
    {
      key: 'month',
      label: t('dashboard.periods.month')
    },
    {
      key: 'year',
      label: t('dashboard.periods.year')
    }
  ])
  const selectedPeriod = ref('month')

  const today = new Date().toLocaleDateString('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })

  const hour = new Date().getHours()
  const greeting = hour < 12 ? 'morning' : hour < 17 ? 'afternoon' : 'evening'
  const userName = computed(
    () => authStore.user?.name?.split(' ')[0] ?? 'there'
  )

  // ── Data ───────────────────────────────────────────────────────────────────────
  const kpis = computed(() => store.stats?.kpis ?? [])
  const branches = computed(() => store.stats?.branches ?? [])

  const branchColors = [
    'primary',
    'success',
    'warning',
    'secondary',
    'info',
    'error'
  ]
  const branchColor = i => branchColors[i % branchColors.length]

  // ── Quick Actions ──────────────────────────────────────────────────────────────
  const quickActions = [
    {
      label: t('dashboard.actions.add_product'),
      icon: 'mdi-package-variant-plus',
      color: 'primary',
      route: '/products/create'
    },
    {
      label: t('dashboard.actions.view_reports'),
      icon: 'mdi-chart-bar',
      color: 'secondary',
      route: '/orders-reports'
    },
    {
      label: t('dashboard.actions.manage_staff'),
      icon: 'mdi-account-multiple-plus',
      color: 'info',
      route: '/staff-management'
    }
  ]

  // ── Load & auto-refresh ────────────────────────────────────────────────────────
  const onPeriodChange = () => {
    store.fetchAll(selectedPeriod.value)
  }

  // let liveInterval = null

  onMounted(async () => {
    await store.fetchAll(selectedPeriod.value)
  })
</script>

<style scoped>
  .dashboard-title {
    letter-spacing: -0.5px;
  }
  .live-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #4caf50;
    box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.4);
    animation: pulse-dot 2s infinite;
    flex-shrink: 0;
  }
  @keyframes pulse-dot {
    0% {
      box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.4);
    }
    70% {
      box-shadow: 0 0 0 8px rgba(76, 175, 80, 0);
    }
    100% {
      box-shadow: 0 0 0 0 rgba(76, 175, 80, 0);
    }
  }
  .kpi-card--hero {
    background: linear-gradient(135deg, #1867c0 0%, #1565c0 100%) !important;
    box-shadow: 0 8px 24px rgba(24, 103, 192, 0.3) !important;
  }
  .branch-row {
    transition: background 0.15s;
    cursor: pointer;
  }
  .chart-wrap {
    position: relative;
  }
  .revenue-chart {
    width: 100%;
    height: 180px;
    display: block;
    color: #e0e0e0;
  }
  .chart-labels {
    display: flex;
    justify-content: space-between;
    margin-top: 6px;
    padding: 0 2px;
  }
  .donut-wrap {
    display: flex;
    justify-content: center;
  }
  .donut-svg {
    width: 140px;
    height: 140px;
    transform: rotate(-90deg);
  }
  .donut-total-label {
    transform: rotate(90deg);
    transform-origin: center;
    font-size: 18px;
    font-weight: 900;
    fill: currentColor;
  }
  .donut-sub-label {
    transform: rotate(90deg);
    transform-origin: center;
    font-size: 9px;
    fill: #9e9e9e;
  }
  .donut-legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 3px;
    flex-shrink: 0;
  }
  .quick-action-card {
    cursor: pointer;
    transition:
      transform 0.15s,
      box-shadow 0.15s;
  }
  .quick-action-card:hover {
    transform: translateY(-2px);
  }
  .opacity-80 {
    opacity: 0.8;
  }
  .opacity-60 {
    opacity: 0.6;
  }
  .tracking-wide {
    letter-spacing: 0.08em;
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
