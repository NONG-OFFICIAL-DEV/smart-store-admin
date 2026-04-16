<template>
  <v-container fluid class="pa-0 dashboard">
    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <div class="d-flex align-center justify-space-between mb-0">
      <div>
        <div class="d-flex align-center gap-2 mb-1">
          <div class="live-dot" />
          <span
            class="text-caption text-medium-emphasis font-weight-medium tracking-wide"
          >
            LIVE OVERVIEW
          </span>
        </div>
        <custom-title
          icon="mdi-view-dashboard-outline"
          :title="`Good ${greeting}, ${userName} 👋`"
          :subtitle="`${today} · ${branches.length} branches across your network`"
        ></custom-title>
      </div>
      <div class="d-flex align-center gap-3">
        <v-select
          v-model="selectedPeriod"
          :items="periods"
          variant="outlined"
          density="compact"
          hide-details
          rounded="lg"
          @update:model-value="onPeriodChange"
        />
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-download-outline"
        >
          Export
        </v-btn>
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
            <v-skeleton-loader
              v-if="store.loading.stats"
              type="list-item-two-line"
            />
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
                {{
                  kpi.isCurrency
                    ? format(kpi.value)
                    : kpi.value
                }}
                
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
                vs last {{ selectedPeriod.toLowerCase() }}
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
                  Branch Performance
                </div>
                <div class="text-caption text-medium-emphasis">
                  Revenue & orders per location
                </div>
              </div>
              <v-btn
                variant="text"
                color="primary"
                size="small"
                append-icon="mdi-arrow-right"
                @click="$router.push('/branches')"
              >
                View All
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
                    Top
                  </v-chip>
                </v-list-item-title>
                <v-list-item-subtitle class="text-caption">
                  {{ branch.city }} · {{ branch.orders }} orders today
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
                No branches found
              </div>
            </template>
          </v-list>
        </v-card>
      </v-col>
            <v-col cols="12" lg="4">
        <v-card rounded="xl" elevation="0" border height="100%">
          <v-card-title class="pa-5 pb-3">
            <div class="text-subtitle-1 font-weight-bold">Quick Actions</div>
            <div class="text-caption text-medium-emphasis">
              Shortcuts for common tasks
            </div>
          </v-card-title>
          <v-card-text class="pa-4 pt-2">
            <v-row dense>
              <v-col
                v-for="action in quickActions"
                :key="action.label"
                cols="6"
              >
                <v-card
                  rounded="xl"
                  elevation="0"
                  :color="action.color"
                  variant="tonal"
                  class="quick-action-card pa-4 text-center"
                  hover
                  @click="$router.push(action.route)"
                >
                  <v-icon :icon="action.icon" size="28" class="mb-2" />
                  <div class="text-body-2 font-weight-semibold">
                    {{ action.label }}
                  </div>
                </v-card>
              </v-col>
            </v-row>
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

  const { format } = useCurrency()
  // ── Period ─────────────────────────────────────────────────────────────────────
  const periods = ['Today', 'Week', 'Month', 'Year']
  const selectedPeriod = ref('Month')

  const today = new Date().toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
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
      label: 'Add Product',
      icon: 'mdi-package-variant-plus',
      color: 'primary',
      route: '/products'
    },
    {
      label: 'New Branch',
      icon: 'mdi-store-plus',
      color: 'success',
      route: '/branches'
    },
    {
      label: 'Assign Menu',
      icon: 'mdi-book-plus-outline',
      color: 'warning',
      route: '/menus'
    },
    {
      label: 'View Reports',
      icon: 'mdi-chart-bar',
      color: 'secondary',
      route: '/reports'
    },
    {
      label: 'Manage Staff',
      icon: 'mdi-account-multiple-plus',
      color: 'info',
      route: '/staff'
    },
    {
      label: 'Settings',
      icon: 'mdi-cog-outline',
      color: 'error',
      route: '/settings'
    }
  ]

  // ── Load & auto-refresh ────────────────────────────────────────────────────────
  const onPeriodChange = () =>
    store.fetchAll(selectedPeriod.value.toLowerCase())

  // let liveInterval = null

  onMounted(async () => {
    await store.fetchAll(selectedPeriod.value.toLowerCase())
    store.fetchLiveOrders()
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
