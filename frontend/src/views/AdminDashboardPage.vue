<template>
  <v-container fluid class="pa-0">
    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <div class="d-flex align-center justify-space-between mb-7">
      <div>
        <div class="d-flex align-center gap-2 mb-1">
          <v-icon icon="mdi-shield-crown" size="16" color="warning" />
          <span
            class="text-caption font-weight-bold tracking-wide text-warning"
          >
            SUPER ADMIN
          </span>
        </div>
        <h1 class="text-h4 font-weight-black admin-title">Platform Overview</h1>
        <p class="text-body-2 text-medium-emphasis mt-1">
          {{ today }} ·
          {{
            systemStatus.healthy
              ? 'All systems operational'
              : 'System issues detected'
          }}
          <v-icon
            :icon="
              systemStatus.healthy ? 'mdi-check-circle' : 'mdi-alert-circle'
            "
            :color="systemStatus.healthy ? 'success' : 'error'"
            size="14"
            class="ml-1"
          />
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
          variant="tonal"
          rounded="lg"
          class="ms-2"
          prepend-icon="mdi-download-outline"
        >
          Export Report
        </v-btn>
        <v-btn
          color="warning"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          class="ms-2"
        >
          New Tenant
        </v-btn>
      </div>
    </div>

    <!-- ── KPI Row ────────────────────────────────────────────────────────── -->
    <v-row dense class="mb-5">
      <v-col v-for="(kpi, i) in kpis" :key="kpi.label" cols="6" sm="4" lg="2">
        <v-card
          rounded="xl"
          elevation="0"
          :class="['kpi-card', i === 0 ? 'kpi-hero' : '']"
          border
        >
          <v-card-text class="pa-4">
            <div class="d-flex align-center justify-space-between mb-3">
              <v-avatar
                :color="i === 0 ? 'rgba(255,193,7,0.25)' : kpi.color"
                size="36"
                rounded="lg"
              >
                <v-icon
                  :icon="kpi.icon"
                  size="18"
                  :color="i === 0 ? 'warning' : 'white'"
                />
              </v-avatar>
              <v-chip
                :color="kpi.trend > 0 ? 'success' : 'error'"
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
                i === 0 ? 'text-warning' : ''
              ]"
            >
              {{ kpi.value }}
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
              {{ kpi.label }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row dense>
      <!-- ── Left: MRR Chart + Tenant Table ──────────────────────────────── -->
      <v-col cols="12" lg="8">
        <!-- MRR / Revenue Chart -->
        <v-card rounded="xl" elevation="0" border class="mb-4">
          <v-card-title class="pa-5 pb-3">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-subtitle-1 font-weight-bold">
                  Revenue Growth
                </div>
                <div class="text-caption text-medium-emphasis">
                  MRR across all tenants
                </div>
              </div>
              <div class="d-flex align-center gap-3">
                <div class="d-flex align-center gap-1">
                  <div class="legend-dot" style="background: #ffc107" />
                  <span class="text-caption text-medium-emphasis">MRR</span>
                </div>
                <div class="d-flex align-center gap-1">
                  <div class="legend-dot" style="background: #4caf50" />
                  <span class="text-caption text-medium-emphasis">New MRR</span>
                </div>
                <v-btn-toggle
                  v-model="chartPeriod"
                  mandatory
                  density="compact"
                  rounded="lg"
                  variant="outlined"
                  color="warning"
                >
                  <v-btn value="6m" size="x-small">6M</v-btn>
                  <v-btn value="1y" size="x-small">1Y</v-btn>
                </v-btn-toggle>
              </div>
            </div>
          </v-card-title>
          <v-card-text class="pa-5 pt-2">
            <div class="chart-wrap">
              <svg
                viewBox="0 0 700 200"
                preserveAspectRatio="none"
                class="mrr-chart"
              >
                <defs>
                  <linearGradient id="mrrGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#FFC107" stop-opacity="0.3" />
                    <stop offset="100%" stop-color="#FFC107" stop-opacity="0" />
                  </linearGradient>
                  <linearGradient id="newMrrGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#4CAF50" stop-opacity="0.2" />
                    <stop offset="100%" stop-color="#4CAF50" stop-opacity="0" />
                  </linearGradient>
                </defs>
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
                <!-- MRR area -->
                <path :d="mrrAreaPath" fill="url(#mrrGrad)" />
                <path
                  :d="mrrLinePath"
                  fill="none"
                  stroke="#FFC107"
                  stroke-width="2.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
                <!-- New MRR area -->
                <path :d="newMrrAreaPath" fill="url(#newMrrGrad)" />
                <path
                  :d="newMrrLinePath"
                  fill="none"
                  stroke="#4CAF50"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-dasharray="5,3"
                />
                <!-- Dots MRR -->
                <circle
                  v-for="(pt, i) in mrrPoints"
                  :key="`m${i}`"
                  :cx="pt.x"
                  :cy="pt.y"
                  r="4"
                  fill="white"
                  stroke="#FFC107"
                  stroke-width="2"
                />
              </svg>
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

        <!-- Tenants Table -->
        <v-card rounded="xl" elevation="0" border>
          <v-card-title class="pa-5 pb-3">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-subtitle-1 font-weight-bold">Tenants</div>
                <div class="text-caption text-medium-emphasis">
                  All registered businesses
                </div>
              </div>
              <div class="d-flex align-center gap-2">
                <v-text-field
                  v-model="tenantSearch"
                  placeholder="Search..."
                  prepend-inner-icon="mdi-magnify"
                  variant="outlined"
                  density="compact"
                  hide-details
                  rounded="lg"
                  style="width: 180px"
                  clearable
                />
                <v-btn
                  variant="text"
                  color="warning"
                  size="small"
                  append-icon="mdi-arrow-right"
                >
                  View All
                </v-btn>
              </div>
            </div>
          </v-card-title>
          <v-divider />
          <v-list class="pa-2">
            <v-list-item
              v-for="tenant in filteredTenants"
              :key="tenant.id"
              rounded="lg"
              class="mb-1"
            >
              <template #prepend>
                <v-avatar
                  :color="tenant.color"
                  size="40"
                  rounded="lg"
                  class="mr-3"
                >
                  <v-icon
                    :icon="
                      tenant.industry === 'food' ? 'mdi-food' : 'mdi-shopping'
                    "
                    size="20"
                    color="white"
                  />
                </v-avatar>
              </template>

              <v-list-item-title class="font-weight-medium text-body-2">
                {{ tenant.name }}
                <v-chip
                  :color="planColor(tenant.plan)"
                  variant="tonal"
                  size="x-small"
                  rounded="lg"
                  class="ml-2"
                >
                  {{ tenant.plan }}
                </v-chip>
              </v-list-item-title>
              <v-list-item-subtitle class="text-caption">
                {{ tenant.branches }} branches · {{ tenant.products }} products
                · joined {{ tenant.joined }}
              </v-list-item-subtitle>

              <template #append>
                <div class="d-flex align-center gap-4">
                  <div class="text-right" style="min-width: 90px">
                    <div class="font-weight-bold text-body-2">
                      ${{ tenant.mrr }}
                      <span class="text-caption text-medium-emphasis">/mo</span>
                    </div>
                    <div
                      class="text-caption"
                      :class="
                        tenant.growth >= 0 ? 'text-success' : 'text-error'
                      "
                    >
                      <v-icon
                        :icon="
                          tenant.growth >= 0
                            ? 'mdi-trending-up'
                            : 'mdi-trending-down'
                        "
                        size="11"
                      />
                      {{ Math.abs(tenant.growth) }}%
                    </div>
                  </div>
                  <v-chip
                    :color="
                      tenant.status === 'active'
                        ? 'success'
                        : tenant.status === 'trial'
                          ? 'warning'
                          : 'error'
                    "
                    variant="tonal"
                    size="small"
                    rounded="lg"
                  >
                    {{ tenant.status }}
                  </v-chip>
                  <v-btn icon="mdi-dots-vertical" size="small" variant="text" />
                </div>
              </template>
            </v-list-item>
          </v-list>
        </v-card>
      </v-col>

 
    </v-row>

    <!-- ── Bottom Row ─────────────────────────────────────────────────────── -->
    <v-row dense class="mt-1">
      <!-- Churn / Retention -->
      <v-col cols="12" sm="6" lg="3">
        <v-card rounded="xl" elevation="0" border height="100%">
          <v-card-title class="pa-5 pb-3">
            <div class="text-subtitle-1 font-weight-bold">Retention</div>
            <div class="text-caption text-medium-emphasis">This month</div>
          </v-card-title>
          <v-card-text class="pa-5 pt-0">
            <div class="donut-wrap">
              <svg viewBox="0 0 120 120" class="donut-svg">
                <circle
                  cx="60"
                  cy="60"
                  r="45"
                  fill="none"
                  stroke="#f5f5f5"
                  stroke-width="14"
                />
                <circle
                  cx="60"
                  cy="60"
                  r="45"
                  fill="none"
                  stroke="#4CAF50"
                  stroke-width="14"
                  :stroke-dasharray="`${retentionDash} ${retentionGap}`"
                  stroke-dashoffset="-70.7"
                  stroke-linecap="butt"
                />
                <text
                  x="60"
                  y="55"
                  text-anchor="middle"
                  class="donut-total-label"
                >
                  94%
                </text>
                <text
                  x="60"
                  y="70"
                  text-anchor="middle"
                  class="donut-sub-label"
                >
                  retained
                </text>
              </svg>
            </div>
            <div class="mt-3">
              <div class="d-flex justify-space-between mb-2">
                <div class="d-flex align-center gap-2">
                  <div class="legend-dot" style="background: #4caf50" />
                  <span class="text-body-2">Retained</span>
                </div>
                <span class="font-weight-bold text-success">118</span>
              </div>
              <div class="d-flex justify-space-between mb-2">
                <div class="d-flex align-center gap-2">
                  <div class="legend-dot" style="background: #f44336" />
                  <span class="text-body-2">Churned</span>
                </div>
                <span class="font-weight-bold text-error">7</span>
              </div>
              <div class="d-flex justify-space-between">
                <div class="d-flex align-center gap-2">
                  <div class="legend-dot" style="background: #ffc107" />
                  <span class="text-body-2">Trial</span>
                </div>
                <span class="font-weight-bold text-warning">14</span>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Top tenants by GMV -->
      <v-col cols="12" sm="6" lg="5">
        <v-card rounded="xl" elevation="0" border height="100%">
          <v-card-title class="pa-5 pb-3">
            <div class="text-subtitle-1 font-weight-bold">
              Top Tenants by GMV
            </div>
            <div class="text-caption text-medium-emphasis">
              Gross merchandise value this {{ selectedPeriod.toLowerCase() }}
            </div>
          </v-card-title>
          <v-divider />
          <v-list density="compact" class="pa-2">
            <v-list-item
              v-for="(t, i) in topTenants"
              :key="t.name"
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
                {{ t.name }}
              </v-list-item-title>
              <v-list-item-subtitle class="text-caption">
                {{ t.branches }} branches ·
                {{ t.orders.toLocaleString() }} orders
              </v-list-item-subtitle>
              <template #append>
                <div class="text-right">
                  <div class="text-body-2 font-weight-bold">
                    ${{ t.gmv.toLocaleString() }}
                  </div>
                  <v-chip
                    :color="t.growth >= 0 ? 'success' : 'error'"
                    variant="tonal"
                    size="x-small"
                    rounded="lg"
                  >
                    <v-icon
                      :icon="
                        t.growth >= 0 ? 'mdi-trending-up' : 'mdi-trending-down'
                      "
                      size="10"
                      class="mr-1"
                    />
                    {{ Math.abs(t.growth) }}%
                  </v-chip>
                </div>
              </template>
            </v-list-item>
          </v-list>
        </v-card>
      </v-col>

      <!-- Admin Quick Actions -->
      <v-col cols="12" lg="4">
        <v-card rounded="xl" elevation="0" border height="100%">
          <v-card-title class="pa-5 pb-3">
            <div class="text-subtitle-1 font-weight-bold">Admin Actions</div>
            <div class="text-caption text-medium-emphasis">
              Platform management shortcuts
            </div>
          </v-card-title>
          <v-card-text class="pa-4 pt-2">
            <v-row dense>
              <v-col
                v-for="action in adminActions"
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
                >
                  <v-icon :icon="action.icon" size="26" class="mb-2" />
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
  const selectedPeriod = ref('Month')
  const chartPeriod = ref('6m')
  const tenantSearch = ref('')

  const today = new Date().toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })

  // ── System Status ─────────────────────────────────────────────────────────────
  const systemStatus = ref({ healthy: true })

  const services = ref([
    { name: 'API Gateway', status: 'up', uptime: 99.98, latency: '42ms' },
    { name: 'Database', status: 'up', uptime: 99.95, latency: '8ms' },
    { name: 'Auth Service', status: 'up', uptime: 100, latency: '21ms' },
    {
      name: 'Storage / CDN',
      status: 'degraded',
      uptime: 98.2,
      latency: '180ms'
    },
    { name: 'Email Service', status: 'up', uptime: 99.9, latency: '55ms' }
  ])

  // ── KPIs ──────────────────────────────────────────────────────────────────────
  const kpis = computed(() => [
    {
      label: 'MRR',
      value: '$24,860',
      icon: 'mdi-cash-multiple',
      color: 'warning',
      trend: +18.4
    },
    {
      label: 'Total Tenants',
      value: '139',
      icon: 'mdi-domain',
      color: 'primary',
      trend: +12.1
    },
    {
      label: 'Active Branches',
      value: '418',
      icon: 'mdi-store',
      color: 'success',
      trend: +9.3
    },
    {
      label: 'Platform GMV',
      value: '$1.2M',
      icon: 'mdi-chart-line',
      color: 'teal',
      trend: +22.7
    },
    {
      label: 'Avg Revenue/T',
      value: '$179',
      icon: 'mdi-calculator-variant',
      color: 'secondary',
      trend: +5.8
    },
    {
      label: 'Churn Rate',
      value: '4.7%',
      icon: 'mdi-account-minus',
      color: 'error',
      trend: -1.2
    }
  ])

  // ── Tenants ───────────────────────────────────────────────────────────────────
  const tenants = ref([
    {
      id: 't1',
      name: 'Brew & Co Coffee',
      industry: 'food',
      plan: 'Pro',
      branches: 8,
      products: 124,
      mrr: 299,
      growth: +14.2,
      status: 'active',
      joined: 'Jan 2024',
      color: 'brown'
    },
    {
      id: 't2',
      name: 'FashionHub Retail',
      industry: 'retail',
      plan: 'Enterprise',
      branches: 22,
      products: 890,
      mrr: 899,
      growth: +8.1,
      status: 'active',
      joined: 'Mar 2023',
      color: 'purple'
    },
    {
      id: 't3',
      name: 'Tasty Burgers',
      industry: 'food',
      plan: 'Starter',
      branches: 3,
      products: 48,
      mrr: 99,
      growth: -2.1,
      status: 'trial',
      joined: 'Feb 2025',
      color: 'orange'
    },
    {
      id: 't4',
      name: 'Green Grocery',
      industry: 'retail',
      plan: 'Pro',
      branches: 6,
      products: 342,
      mrr: 299,
      growth: +21.5,
      status: 'active',
      joined: 'Jun 2023',
      color: 'green'
    },
    {
      id: 't5',
      name: 'Noodle Palace',
      industry: 'food',
      plan: 'Starter',
      branches: 2,
      products: 35,
      mrr: 99,
      growth: +5.0,
      status: 'active',
      joined: 'Nov 2024',
      color: 'red'
    },
    {
      id: 't6',
      name: 'TechGear Store',
      industry: 'retail',
      plan: 'Enterprise',
      branches: 15,
      products: 560,
      mrr: 899,
      growth: +31.2,
      status: 'active',
      joined: 'Aug 2022',
      color: 'blue-grey'
    }
  ])

  const filteredTenants = computed(() => {
    if (!tenantSearch.value) return tenants.value
    const q = tenantSearch.value.toLowerCase()
    return tenants.value.filter(t => t.name.toLowerCase().includes(q))
  })

  // ── MRR Chart ─────────────────────────────────────────────────────────────────
  const mrrData = [
    14200, 15800, 16400, 17200, 18900, 19800, 20500, 21200, 22100, 22800, 23900,
    24860
  ]
  const newMrrData = [
    1200, 1600, 600, 800, 1700, 900, 700, 700, 900, 700, 1100, 960
  ]
  const chartLabels = computed(() =>
    chartPeriod.value === '6m'
      ? ['Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
      : [
          'Jan',
          'Feb',
          'Mar',
          'Apr',
          'May',
          'Jun',
          'Jul',
          'Aug',
          'Sep',
          'Oct',
          'Nov',
          'Dec'
        ]
  )

  const buildPoints = data => {
    const w = 700,
      h = 180,
      pad = 20
    const max = Math.max(...mrrData)
    return data.map((v, i) => ({
      x: pad + (i / (data.length - 1)) * (w - pad * 2),
      y: h - pad - (v / max) * (h - pad * 2)
    }))
  }

  const mrrPoints = computed(() => buildPoints(mrrData))
  const newMrrPoints = computed(() => buildPoints(newMrrData))

  const buildLine = pts =>
    pts.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x},${p.y}`).join(' ')
  const buildArea = (pts, linePath) => {
    const last = pts[pts.length - 1],
      first = pts[0]
    return `${linePath} L${last.x},200 L${first.x},200 Z`
  }

  const mrrLinePath = computed(() => buildLine(mrrPoints.value))
  const mrrAreaPath = computed(() =>
    buildArea(mrrPoints.value, mrrLinePath.value)
  )
  const newMrrLinePath = computed(() => buildLine(newMrrPoints.value))
  const newMrrAreaPath = computed(() =>
    buildArea(newMrrPoints.value, newMrrLinePath.value)
  )

  // ── New Signups ───────────────────────────────────────────────────────────────
  const newSignups = ref([
    {
      id: 1,
      name: 'Sakura Sushi',
      initials: 'SS',
      plan: 'Starter',
      time: '2h ago',
      color: 'pink'
    },
    {
      id: 2,
      name: 'Metro Electronics',
      initials: 'ME',
      plan: 'Pro',
      time: '5h ago',
      color: 'blue'
    },
    {
      id: 3,
      name: 'Sunrise Bakery',
      initials: 'SB',
      plan: 'Starter',
      time: '1d ago',
      color: 'orange'
    },
    {
      id: 4,
      name: 'LuxeWear',
      initials: 'LW',
      plan: 'Enterprise',
      time: '2d ago',
      color: 'purple'
    }
  ])

  // ── Plan Revenue ──────────────────────────────────────────────────────────────
  const planRevenue = ref([
    { name: 'Enterprise', tenants: 18, mrr: 16182, percent: 100 },
    { name: 'Pro', tenants: 54, mrr: 6146, percent: 38 },
    { name: 'Starter', tenants: 67, mrr: 2532, percent: 16 }
  ])

  // ── Top Tenants by GMV ────────────────────────────────────────────────────────
  const topTenants = ref([
    {
      name: 'FashionHub Retail',
      branches: 22,
      orders: 18420,
      gmv: 284000,
      growth: +31.2
    },
    {
      name: 'TechGear Store',
      branches: 15,
      orders: 12840,
      gmv: 198000,
      growth: +22.7
    },
    {
      name: 'Brew & Co Coffee',
      branches: 8,
      orders: 28900,
      gmv: 142000,
      growth: +14.2
    },
    {
      name: 'Green Grocery',
      branches: 6,
      orders: 9200,
      gmv: 98000,
      growth: +18.5
    },
    {
      name: 'Noodle Palace',
      branches: 2,
      orders: 4800,
      gmv: 42000,
      growth: +5.0
    }
  ])

  // ── Retention donut ───────────────────────────────────────────────────────────
  const circum = 2 * Math.PI * 45
  const retentionDash = computed(() => 0.94 * circum * 0.97)
  const retentionGap = computed(() => circum - retentionDash.value)

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const planColor = p =>
    ({ Enterprise: 'error', Pro: 'warning', Starter: 'primary' })[p] || 'grey'

  // ── Admin Quick Actions ───────────────────────────────────────────────────────
  const adminActions = [
    { label: 'Manage Tenants', icon: 'mdi-domain', color: 'warning' },
    { label: 'Billing Plans', icon: 'mdi-credit-card', color: 'success' },
    { label: 'System Logs', icon: 'mdi-text-box-outline', color: 'primary' },
    { label: 'Feature Flags', icon: 'mdi-flag-outline', color: 'secondary' },
    { label: 'Send Broadcast', icon: 'mdi-bullhorn-outline', color: 'info' },
    { label: 'Impersonate', icon: 'mdi-account-switch', color: 'error' }
  ]
</script>

<style scoped>
  .admin-title {
    letter-spacing: -0.5px;
  }

  .tracking-wide {
    letter-spacing: 0.08em;
  }

  /* Hero KPI */
  .kpi-hero {
    border-color: rgba(255, 193, 7, 0.4) !important;
    background: rgba(255, 193, 7, 0.05) !important;
  }

  /* Live dot */
  .live-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
  }
  .live-dot--green {
    background: #4caf50;
    box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.4);
    animation: pulse-dot 2s infinite;
  }
  .live-dot--red {
    background: #f44336;
    box-shadow: 0 0 0 0 rgba(244, 67, 54, 0.4);
    animation: pulse-dot-red 2s infinite;
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
  @keyframes pulse-dot-red {
    0% {
      box-shadow: 0 0 0 0 rgba(244, 67, 54, 0.4);
    }
    70% {
      box-shadow: 0 0 0 8px rgba(244, 67, 54, 0);
    }
    100% {
      box-shadow: 0 0 0 0 rgba(244, 67, 54, 0);
    }
  }

  /* Chart */
  .chart-wrap {
    position: relative;
  }
  .mrr-chart {
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
  .legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 3px;
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

  /* Quick actions */
  .quick-action-card {
    cursor: pointer;
    transition: transform 0.15s;
  }
  .quick-action-card:hover {
    transform: translateY(-2px);
  }
</style>
