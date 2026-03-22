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
        >
      </custom-title>
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

      <!-- ── Right Column ─────────────────────────────────────────────────── -->
      <v-col cols="12" lg="4">
        <!-- Live Orders -->
        <v-card rounded="xl" elevation="0" border class="mb-4">
          <v-card-title class="pa-5 pb-3">
            <div class="d-flex align-center justify-space-between">
              <div class="d-flex align-center gap-2">
                <div class="live-dot" />
                <div>
                  <div class="text-subtitle-1 font-weight-bold">
                    Live Orders
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Active right now
                  </div>
                </div>
              </div>
              <v-chip color="success" variant="tonal" size="small" rounded="lg">
                {{
                  store.liveOrders.filter(o => o.status === 'preparing').length
                }}
                active
              </v-chip>
            </div>
          </v-card-title>
          <v-divider />
          <v-list
            density="compact"
            class="pa-2"
            style="max-height: 280px; overflow-y: auto"
          >
            <v-skeleton-loader
              v-if="store.loading.liveOrders"
              v-for="n in 4"
              :key="n"
              type="list-item"
              class="mb-1"
            />
            <template v-else-if="store.liveOrders.length">
              <v-list-item
                v-for="order in store.liveOrders"
                :key="order.id"
                rounded="lg"
                class="mb-1"
              >
                <template #prepend>
                  <v-avatar
                    :color="orderStatusColor(order.status)"
                    size="32"
                    rounded="lg"
                    class="mr-2"
                  >
                    <v-icon
                      :icon="orderStatusIcon(order.status)"
                      size="15"
                      color="white"
                    />
                  </v-avatar>
                </template>
                <v-list-item-title class="text-body-2 font-weight-medium">
                  #{{ order.number }} · {{ order.branch }}
                </v-list-item-title>
                <v-list-item-subtitle class="text-caption">
                  {{ order.items }} items · {{ order.ago }}
                </v-list-item-subtitle>
                <template #append>
                  <div class="text-right">
                    <div class="text-body-2 font-weight-bold">
                      ${{ order.total }}
                    </div>
                    <v-chip
                      :color="orderStatusColor(order.status)"
                      variant="tonal"
                      size="x-small"
                      rounded="lg"
                    >
                      {{ order.status }}
                    </v-chip>
                  </div>
                </template>
              </v-list-item>
            </template>
            <div
              v-else
              class="text-center py-6 text-medium-emphasis text-caption"
            >
              No active orders
            </div>
          </v-list>
        </v-card>

        <!-- Top Products -->
        <v-card rounded="xl" elevation="0" border class="mb-4">
          <v-card-title class="pa-5 pb-3">
            <div class="text-subtitle-1 font-weight-bold">Top Products</div>
            <div class="text-caption text-medium-emphasis">
              Best sellers this {{ selectedPeriod.toLowerCase() }}
            </div>
          </v-card-title>
          <v-divider />
          <v-list density="compact" class="pa-2">
            <v-skeleton-loader
              v-if="store.loading.topProducts"
              v-for="n in 5"
              :key="n"
              type="list-item"
              class="mb-1"
            />
            <template v-else-if="store.topProducts.length">
              <v-list-item
                v-for="(prod, i) in store.topProducts"
                :key="prod.name"
                rounded="lg"
                class="mb-1"
              >
                <template #prepend>
                  <v-avatar
                    color="grey-lighten-3"
                    size="34"
                    rounded="lg"
                    class="mr-2"
                  >
                    <img
                      v-if="prod.image_url"
                      :src="prod.image_url"
                      style="width: 100%; height: 100%; object-fit: cover"
                    />
                    <span
                      v-else
                      class="text-caption font-weight-black text-medium-emphasis"
                    >
                      {{ i + 1 }}
                    </span>
                  </v-avatar>
                </template>
                <v-list-item-title class="text-body-2 font-weight-medium">
                  {{ prod.name }}
                </v-list-item-title>
                <v-list-item-subtitle class="text-caption">
                  {{ prod.sold }} sold
                </v-list-item-subtitle>
                <template #append>
                  <div class="text-right">
                    <div class="text-body-2 font-weight-bold text-success">
                      ${{ prod.revenue?.toLocaleString() }}
                    </div>
                    <div class="text-caption text-medium-emphasis">revenue</div>
                  </div>
                </template>
              </v-list-item>
            </template>
            <div
              v-else
              class="text-center py-6 text-medium-emphasis text-caption"
            >
              No data yet
            </div>
          </v-list>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Bottom Row ─────────────────────────────────────────────────────── -->
    <v-row dense class="mt-1">
      <!-- Donut -->
      <v-col cols="12" sm="6" lg="3">
        <v-card rounded="xl" elevation="0" border height="100%">
          <v-card-title class="pa-5 pb-3">
            <div class="text-subtitle-1 font-weight-bold">Orders by Type</div>
            <div class="text-caption text-medium-emphasis">
              Today's breakdown
            </div>
          </v-card-title>
          <v-card-text class="pa-5 pt-0">
            <v-skeleton-loader
              v-if="store.loading.stats"
              type="image"
              height="140"
            />
            <template v-else>
              <div class="donut-wrap">
                <svg viewBox="0 0 120 120" class="donut-svg">
                  <circle
                    cx="60"
                    cy="60"
                    r="45"
                    fill="none"
                    stroke="#f5f5f5"
                    stroke-width="18"
                  />
                  <circle
                    v-for="(seg, i) in donutSegments"
                    :key="i"
                    cx="60"
                    cy="60"
                    r="45"
                    fill="none"
                    :stroke="seg.color"
                    stroke-width="18"
                    :stroke-dasharray="`${seg.dash} ${seg.gap}`"
                    :stroke-dashoffset="seg.offset"
                    stroke-linecap="butt"
                  />
                  <text
                    x="60"
                    y="55"
                    text-anchor="middle"
                    class="donut-total-label"
                  >
                    {{ totalOrdersToday }}
                  </text>
                  <text
                    x="60"
                    y="70"
                    text-anchor="middle"
                    class="donut-sub-label"
                  >
                    orders
                  </text>
                </svg>
              </div>
              <div class="mt-3">
                <div
                  v-for="seg in donutSegments"
                  :key="seg.label"
                  class="d-flex align-center justify-space-between mb-2"
                >
                  <div class="d-flex align-center gap-2">
                    <div
                      class="donut-legend-dot"
                      :style="{ background: seg.color }"
                    />
                    <span class="text-body-2">{{ seg.label }}</span>
                  </div>
                  <span class="text-body-2 font-weight-bold">
                    {{ seg.count }}
                  </span>
                </div>
              </div>
            </template>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Recent Activity -->
      <v-col cols="12" sm="6" lg="5">
        <v-card rounded="xl" elevation="0" border height="100%">
          <v-card-title class="pa-5 pb-3">
            <div class="text-subtitle-1 font-weight-bold">Recent Activity</div>
            <div class="text-caption text-medium-emphasis">
              Latest events across branches
            </div>
          </v-card-title>
          <v-divider />
          <v-skeleton-loader
            v-if="store.loading.activity"
            type="list-item-three-line"
            class="pa-4"
          />
          <v-timeline
            v-else-if="store.activity.length"
            density="compact"
            side="end"
            class="pa-4"
            truncate-line="both"
          >
            <v-timeline-item
              v-for="event in store.activity"
              :key="event.id"
              :dot-color="event.color"
              size="x-small"
            >
              <div class="d-flex align-center justify-space-between">
                <div>
                  <p class="text-body-2 font-weight-medium">
                    {{ event.title }}
                  </p>
                  <p class="text-caption text-medium-emphasis">
                    {{ event.desc }}
                  </p>
                </div>
                <span
                  class="text-caption text-disabled ml-3"
                  style="white-space: nowrap"
                >
                  {{ event.time }}
                </span>
              </div>
            </v-timeline-item>
          </v-timeline>
          <div
            v-else
            class="text-center py-8 text-medium-emphasis text-caption"
          >
            No recent activity
          </div>
        </v-card>
      </v-col>

      <!-- Quick Actions -->
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
  import { ref, computed, onMounted, onUnmounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useDashboardStore } from '@/stores/dashboardStore'
  import { useAuthStore } from '@/stores/authStore'
  import RevenueChart from '@/components/dashboard/RevenueChart.vue'
  const store = useDashboardStore()
  const authStore = useAuthStore()

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

  // ── Donut ──────────────────────────────────────────────────────────────────────
  const totalOrdersToday = computed(() => store.stats?.total_orders_today ?? 0)
  const donutRaw = computed(() => store.stats?.orders_by_type ?? [])
  const circum = 2 * Math.PI * 45

  const donutSegments = computed(() => {
    const total = donutRaw.value.reduce((s, d) => s + d.count, 0) || 1
    let offset = -circum * 0.25
    return donutRaw.value.map(d => {
      const dash = (d.count / total) * circum * 0.97
      const gap = circum - dash
      const seg = { ...d, dash, gap, offset: -offset }
      offset += (d.count / total) * circum
      return seg
    })
  })

  // ── Order helpers ──────────────────────────────────────────────────────────────
  const orderStatusColor = s =>
    ({
      preparing: 'warning',
      ready: 'success',
      completed: 'grey',
      cancelled: 'error'
    })[s] ?? 'grey'
  const orderStatusIcon = s =>
    ({
      preparing: 'mdi-chef-hat',
      ready: 'mdi-check-circle',
      completed: 'mdi-receipt',
      cancelled: 'mdi-close-circle'
    })[s] ?? 'mdi-circle'

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
