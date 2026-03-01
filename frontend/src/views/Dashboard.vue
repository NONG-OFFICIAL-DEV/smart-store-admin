<template>
  <v-container fluid class="pa-0 dashboard">
    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <div class="d-flex align-center justify-space-between mb-7">
      <div>
        <div class="d-flex align-center gap-2 mb-1">
          <div class="live-dot" />
          <span
            class="text-caption text-medium-emphasis font-weight-medium tracking-wide"
          >
            LIVE OVERVIEW
          </span>
        </div>
        <h1 class="text-h4 font-weight-black dashboard-title">
          Good morning, Alex 👋
        </h1>
        <p class="text-body-2 text-medium-emphasis mt-1">
          {{ today }} · {{ branches.length }} branches active across your
          network
        </p>
      </div>
      <div class="d-flex align-center gap-3">
        <v-select
          v-model="selectedPeriod"
          :items="periods"
          variant="outlined"
          density="compact"
          hide-details
          rounded="lg"
        />
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          class="ms-2"
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
          :class="['kpi-card', i === 0 ? 'kpi-card--hero' : '']"
          :color="i === 0 ? 'primary' : undefined"
          border
        >
          <v-card-text class="pa-5">
            <div class="d-flex align-center justify-space-between mb-3">
              <v-avatar
                :color="i === 0 ? 'rgba(255,255,255,0.2)' : kpi.color"
                size="40"
                rounded="lg"
              >
                <v-icon
                  :icon="kpi.icon"
                  size="20"
                  :color="i === 0 ? 'white' : 'white'"
                />
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
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row dense>
      <!-- ── Revenue Chart ────────────────────────────────────────────────── -->
      <v-col cols="12" lg="8">
        <v-card rounded="xl" elevation="0" border class="mb-4">
          <v-card-title class="pa-5 pb-3">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-subtitle-1 font-weight-bold">
                  Revenue Overview
                </div>
                <div class="text-caption text-medium-emphasis">
                  All branches combined
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
                <v-btn value="revenue" size="small">Revenue</v-btn>
                <v-btn value="orders" size="small">Orders</v-btn>
              </v-btn-toggle>
            </div>
          </v-card-title>
          <v-card-text class="pa-5 pt-2">
            <!-- SVG Chart -->
            <div class="chart-wrap">
              <svg
                viewBox="0 0 700 200"
                preserveAspectRatio="none"
                class="revenue-chart"
              >
                <defs>
                  <linearGradient id="chartGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop
                      offset="0%"
                      stop-color="#1867C0"
                      stop-opacity="0.25"
                    />
                    <stop offset="100%" stop-color="#1867C0" stop-opacity="0" />
                  </linearGradient>
                </defs>
                <!-- Grid lines -->
                <line
                  v-for="y in [40, 80, 120, 160]"
                  :key="y"
                  x1="0"
                  :y1="y"
                  x2="700"
                  :y2="y"
                  stroke="currentColor"
                  stroke-opacity="0.06"
                  stroke-width="1"
                />
                <!-- Area fill -->
                <path :d="chartAreaPath" fill="url(#chartGrad)" />
                <!-- Line -->
                <path
                  :d="chartLinePath"
                  fill="none"
                  stroke="#1867C0"
                  stroke-width="2.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
                <!-- Dots -->
                <circle
                  v-for="(pt, i) in chartPoints"
                  :key="i"
                  :cx="pt.x"
                  :cy="pt.y"
                  r="4"
                  fill="white"
                  stroke="#1867C0"
                  stroke-width="2"
                />
              </svg>
              <!-- X labels -->
              <div class="chart-labels">
                <span
                  v-for="l in chartLabels"
                  :key="l"
                  class="text-caption text-medium-emphasis"
                >
                  {{ l }}
                </span>
              </div>
            </div>
          </v-card-text>
        </v-card>

        <!-- ── Branch Performance Table ───────────────────────────────────── -->
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
              >
                View All
              </v-btn>
            </div>
          </v-card-title>
          <v-divider />
          <v-list class="pa-2">
            <v-list-item
              v-for="branch in branches"
              :key="branch.id"
              rounded="lg"
              class="mb-1 branch-row"
            >
              <template #prepend>
                <v-avatar
                  :color="branch.color"
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
                  <!-- Progress bar -->
                  <div style="width: 100px">
                    <div class="d-flex justify-space-between mb-1">
                      <span class="text-caption text-medium-emphasis">
                        {{ branch.revenuePercent }}%
                      </span>
                    </div>
                    <v-progress-linear
                      :model-value="branch.revenuePercent"
                      :color="branch.color"
                      rounded
                      height="6"
                      bg-color="grey-lighten-3"
                    />
                  </div>
                  <div class="text-right" style="min-width: 80px">
                    <div class="font-weight-bold text-body-2">
                      ${{ branch.revenue.toLocaleString() }}
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
          </v-list>
        </v-card>
      </v-col>

      <!-- ── Right Column ─────────────────────────────────────────────────── -->
      <v-col cols="12" lg="4">
        <!-- Live Orders Feed -->
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
                    Updating in real-time
                  </div>
                </div>
              </div>
              <v-chip color="success" variant="tonal" size="small" rounded="lg">
                {{
                  liveOrders.filter(o => o.status === 'preparing').length
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
            <v-list-item
              v-for="order in liveOrders"
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
            <v-list-item
              v-for="(prod, i) in topProducts"
              :key="prod.name"
              rounded="lg"
              class="mb-1"
            >
              <template #prepend>
                <v-avatar
                  color="grey-lighten-3"
                  size="32"
                  rounded="lg"
                  class="mr-2"
                >
                  <span
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
                    ${{ prod.revenue.toLocaleString() }}
                  </div>
                  <div class="text-caption text-medium-emphasis">revenue</div>
                </div>
              </template>
            </v-list-item>
          </v-list>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Bottom Row ─────────────────────────────────────────────────────── -->
    <v-row dense class="mt-1">
      <!-- Orders by Type donut -->
      <v-col cols="12" sm="6" lg="3">
        <v-card rounded="xl" elevation="0" border height="100%">
          <v-card-title class="pa-5 pb-3">
            <div class="text-subtitle-1 font-weight-bold">Orders by Type</div>
            <div class="text-caption text-medium-emphasis">
              Today's breakdown
            </div>
          </v-card-title>
          <v-card-text class="pa-5 pt-0">
            <!-- Donut SVG -->
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
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Recent activity -->
      <v-col cols="12" sm="6" lg="5">
        <v-card rounded="xl" elevation="0" border height="100%">
          <v-card-title class="pa-5 pb-3">
            <div class="text-subtitle-1 font-weight-bold">Recent Activity</div>
            <div class="text-caption text-medium-emphasis">
              Latest events across branches
            </div>
          </v-card-title>
          <v-divider />
          <v-timeline
            density="compact"
            side="end"
            class="pa-4"
            truncate-line="both"
          >
            <v-timeline-item
              v-for="event in recentActivity"
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
                  @click="action.fn"
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
  import { ref, computed } from 'vue'

  // ── Period ────────────────────────────────────────────────────────────────────
  const periods = ['Today', 'Week', 'Month', 'Year']
  const selectedPeriod = ref('Week')

  const today = new Date().toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })

  // ── KPIs ──────────────────────────────────────────────────────────────────────
  const kpis = computed(() => [
    {
      label: 'Total Revenue',
      value: '$48,320',
      icon: 'mdi-cash-multiple',
      color: 'primary',
      trend: +12.4
    },
    {
      label: 'Total Orders',
      value: '1,284',
      icon: 'mdi-receipt-outline',
      color: 'success',
      trend: +8.1
    },
    {
      label: 'Avg Order Value',
      value: '$37.60',
      icon: 'mdi-calculator-variant',
      color: 'warning',
      trend: +3.2
    },
    {
      label: 'Active Products',
      value: '142',
      icon: 'mdi-package-variant',
      color: 'secondary',
      trend: -2.0
    }
  ])

  // ── Branches ──────────────────────────────────────────────────────────────────
  const branches = ref([
    {
      id: 'b1',
      name: 'Downtown Central',
      city: 'Bangkok',
      revenue: 18400,
      orders: 312,
      revenuePercent: 92,
      growth: +14.2,
      color: 'primary',
      rank: 1
    },
    {
      id: 'b2',
      name: 'Siam Square',
      city: 'Bangkok',
      revenue: 13200,
      orders: 228,
      revenuePercent: 66,
      growth: +7.8,
      color: 'success',
      rank: 2
    },
    {
      id: 'b3',
      name: 'On Nut Branch',
      city: 'Bangkok',
      revenue: 9800,
      orders: 185,
      revenuePercent: 49,
      growth: -2.1,
      color: 'warning',
      rank: 3
    },
    {
      id: 'b4',
      name: 'Chiang Mai Hub',
      city: 'Chiang Mai',
      revenue: 6920,
      orders: 134,
      revenuePercent: 35,
      growth: +18.5,
      color: 'secondary',
      rank: 4
    }
  ])

  // ── Revenue Chart ─────────────────────────────────────────────────────────────
  const chartMode = ref('revenue')
  const chartData = [38, 52, 44, 65, 58, 72, 68, 85, 76, 90, 82, 95, 88, 100]
  const chartLabels = [
    'Mon',
    'Tue',
    'Wed',
    'Thu',
    'Fri',
    'Sat',
    'Sun',
    'Mon',
    'Tue',
    'Wed',
    'Thu',
    'Fri',
    'Sat',
    'Sun'
  ]

  const chartPoints = computed(() => {
    const w = 700,
      h = 180,
      pad = 20
    const max = Math.max(...chartData)
    return chartData.map((v, i) => ({
      x: pad + (i / (chartData.length - 1)) * (w - pad * 2),
      y: h - pad - (v / max) * (h - pad * 2)
    }))
  })

  const chartLinePath = computed(() => {
    return chartPoints.value
      .map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x},${p.y}`)
      .join(' ')
  })

  const chartAreaPath = computed(() => {
    const pts = chartPoints.value
    const last = pts[pts.length - 1]
    const first = pts[0]
    return `${chartLinePath.value} L${last.x},200 L${first.x},200 Z`
  })

  // ── Live Orders ───────────────────────────────────────────────────────────────
  const liveOrders = ref([
    {
      id: 1,
      number: '4821',
      branch: 'Downtown',
      items: 3,
      total: '42.50',
      status: 'preparing',
      ago: '2 min ago'
    },
    {
      id: 2,
      number: '4820',
      branch: 'Siam',
      items: 1,
      total: '12.00',
      status: 'ready',
      ago: '5 min ago'
    },
    {
      id: 3,
      number: '4819',
      branch: 'On Nut',
      items: 5,
      total: '78.00',
      status: 'preparing',
      ago: '7 min ago'
    },
    {
      id: 4,
      number: '4818',
      branch: 'Chiang Mai',
      items: 2,
      total: '28.50',
      status: 'completed',
      ago: '9 min ago'
    },
    {
      id: 5,
      number: '4817',
      branch: 'Downtown',
      items: 4,
      total: '55.00',
      status: 'completed',
      ago: '11 min ago'
    }
  ])

  const orderStatusColor = s =>
    ({
      preparing: 'warning',
      ready: 'success',
      completed: 'grey',
      cancelled: 'error'
    })[s] || 'grey'
  const orderStatusIcon = s =>
    ({
      preparing: 'mdi-chef-hat',
      ready: 'mdi-check-circle',
      completed: 'mdi-receipt',
      cancelled: 'mdi-close-circle'
    })[s] || 'mdi-circle'

  // ── Top Products ──────────────────────────────────────────────────────────────
  const topProducts = ref([
    { name: 'Margherita Pizza', sold: 284, revenue: 3689 },
    { name: 'Espresso', sold: 412, revenue: 1442 },
    { name: 'Chicken Burger', sold: 198, revenue: 2574 },
    { name: 'Caesar Salad', sold: 156, revenue: 1716 },
    { name: 'Branded Tote Bag', sold: 89, revenue: 890 }
  ])


  // ── Donut Chart ───────────────────────────────────────────────────────────────
  const totalOrdersToday = 859
  const donutRaw = [
    { label: 'Dine-in', count: 412, color: '#1867C0' },
    { label: 'Takeaway', count: 298, color: '#00897B' },
    { label: 'Delivery', count: 149, color: '#FB8C00' }
  ]
  const circum = 2 * Math.PI * 45 // ~282.7

  const donutSegments = computed(() => {
    const total = donutRaw.reduce((s, d) => s + d.count, 0)
    let offset = -circum * 0.25 // start at top
    return donutRaw.map(d => {
      const dash = (d.count / total) * circum * 0.97
      const gap = circum - dash
      const seg = { ...d, dash, gap, offset: -offset }
      offset += (d.count / total) * circum
      return seg
    })
  })

  // ── Recent Activity ───────────────────────────────────────────────────────────
  const recentActivity = ref([
    {
      id: 1,
      title: 'New branch menu assigned',
      desc: 'Siam Square · Lunch Menu',
      color: 'primary',
      time: '2m ago'
    },
    {
      id: 2,
      title: 'Product marked unavailable',
      desc: 'Mozzarella Pizza · Downtown',
      color: 'error',
      time: '15m ago'
    },
    {
      id: 3,
      title: 'High order volume alert',
      desc: 'Downtown · 300+ orders today',
      color: 'warning',
      time: '1h ago'
    },
    {
      id: 4,
      title: 'New product created',
      desc: 'Truffle Fries added to catalog',
      color: 'success',
      time: '2h ago'
    },
    {
      id: 5,
      title: 'Branch override set',
      desc: 'Chiang Mai · Espresso +$0.50',
      color: 'secondary',
      time: '3h ago'
    }
  ])

  // ── Quick Actions ─────────────────────────────────────────────────────────────
  const quickActions = [
    {
      label: 'Add Product',
      icon: 'mdi-package-variant-plus',
      color: 'primary',
      fn: () => {}
    },
    {
      label: 'New Branch',
      icon: 'mdi-store-plus',
      color: 'success',
      fn: () => {}
    },
    {
      label: 'Assign Menu',
      icon: 'mdi-book-plus-outline',
      color: 'warning',
      fn: () => {}
    },
    {
      label: 'View Reports',
      icon: 'mdi-chart-bar',
      color: 'secondary',
      fn: () => {}
    },
    {
      label: 'Manage Staff',
      icon: 'mdi-account-multiple-plus',
      color: 'info',
      fn: () => {}
    },
    { label: 'Settings', icon: 'mdi-cog-outline', color: 'error', fn: () => {} }
  ]
</script>

<style scoped>
  .dashboard-title {
    letter-spacing: -0.5px;
  }

  /* Live indicator dot */
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

  /* Hero KPI card */
  .kpi-card--hero {
    background: linear-gradient(135deg, #1867c0 0%, #1565c0 100%) !important;
    box-shadow: 0 8px 24px rgba(24, 103, 192, 0.3) !important;
  }

  /* Branch row hover */
  .branch-row {
    transition: background 0.15s;
  }

  /* Revenue chart */
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

  /* Donut */
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

  /* Quick action cards */
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
</style>
