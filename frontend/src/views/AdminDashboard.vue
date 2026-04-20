<template>
  <v-container fluid class="pa-0">
    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <div class="d-flex align-center justify-space-between mb-7">
      <div>
        <div class="d-flex align-center gap-2 mb-1">
          <v-avatar color="primary" size="20" rounded="sm">
            <v-icon icon="mdi-shield-crown-outline" size="12" color="white" />
          </v-avatar>
          <span
            class="text-caption text-medium-emphasis font-weight-bold tracking-wide"
          >
            SUPER ADMIN · PLATFORM OVERVIEW
          </span>
        </div>
        <h1 class="text-h4 font-weight-black" style="letter-spacing: -0.5px">
          Platform Dashboard
        </h1>
        <p class="text-body-2 text-medium-emphasis mt-1">
          {{ today }} · Monitoring all tenants & branches
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
          style="width: 130px"
          @update:model-value="onPeriodChange"
        />
        <v-btn
          variant="tonal"
          rounded="lg"
          prepend-icon="mdi-refresh"
          :loading="store.loading.stats"
          @click="onPeriodChange"
        >
          Refresh
        </v-btn>
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
      <v-col v-for="(kpi, i) in kpis" :key="kpi.label" cols="6" sm="4" lg="2">
        <v-card
          rounded="xl"
          elevation="0"
          border
          :class="['kpi-card', i === 0 ? 'kpi-card--hero' : '']"
          :color="i === 0 ? 'primary' : undefined"
        >
          <v-card-text class="pa-4">
            <v-skeleton-loader
              v-if="store.loading.stats"
              type="list-item-two-line"
            />
            <template v-else>
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
            </template>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row dense>
      <!-- ── Left: Revenue Chart + Tenant table ─────────────────────────── -->
      <v-col cols="12" lg="8">
        <!-- Revenue chart -->
        <v-card rounded="xl" elevation="0" border class="mb-4">
          <v-card-title class="pa-5 pb-3">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-subtitle-1 font-weight-bold">
                  Platform Revenue
                </div>
                <div class="text-caption text-medium-emphasis">
                  All tenants combined
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
            <v-skeleton-loader
              v-if="store.loading.chart"
              type="image"
              height="180"
            />
            <div v-else class="chart-wrap">
              <div class="chart-wrap">
                <canvas ref="chartCanvas" height="180" />
              </div>
            </div>
          </v-card-text>
        </v-card>

        <!-- Tenant performance table -->
        <v-card rounded="xl" elevation="0" border>
          <v-card-title class="pa-5 pb-3">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-subtitle-1 font-weight-bold">
                  Tenant Performance
                </div>
                <div class="text-caption text-medium-emphasis">
                  Revenue by tenant this {{ selectedPeriod.toLowerCase() }}
                </div>
              </div>
              <v-btn
                variant="text"
                color="primary"
                size="small"
                append-icon="mdi-arrow-right"
                @click="$router.push('/tenants')"
              >
                Manage Tenants
              </v-btn>
            </div>
          </v-card-title>
          <v-divider />
          <v-list class="pa-2">
            <v-skeleton-loader
              v-if="store.loading.stats"
              v-for="n in 4"
              :key="n"
              type="list-item-two-line"
              class="mb-1"
            />
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
                <v-list-item-title class="font-weight-medium text-body-2">
                  {{ tenant.name }}
                  <v-chip
                    v-if="tenant.rank === 1"
                    size="x-small"
                    color="warning"
                    variant="tonal"
                    class="ml-2"
                  >
                    <v-icon icon="mdi-crown" size="10" class="mr-1" />
                    Top
                  </v-chip>
                  <v-chip
                    :color="tenant.is_active ? 'success' : 'error'"
                    size="x-small"
                    variant="tonal"
                    class="ml-1"
                  >
                    {{ tenant.is_active ? 'Active' : 'Inactive' }}
                  </v-chip>
                </v-list-item-title>
                <v-list-item-subtitle class="text-caption">
                  {{ tenant.branches_count }} branches ·
                  {{ tenant.orders_today }} orders today
                </v-list-item-subtitle>
                <template #append>
                  <div class="d-flex align-center gap-4">
                    <div style="width: 100px">
                      <div class="d-flex justify-space-between mb-1">
                        <span class="text-caption text-medium-emphasis">
                          {{ tenant.revenuePercent }}%
                        </span>
                      </div>
                      <v-progress-linear
                        :model-value="tenant.revenuePercent"
                        :color="rowColor(idx)"
                        rounded
                        height="6"
                        bg-color="grey-lighten-3"
                      />
                    </div>
                    <div class="text-right" style="min-width: 90px">
                      <div class="font-weight-bold text-body-2">
                        ${{ tenant.revenue?.toLocaleString() }}
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
                  </div>
                </template>
              </v-list-item>
              <div
                v-if="!topTenants.length"
                class="text-center py-6 text-medium-emphasis text-body-2"
              >
                No tenants found
              </div>
            </template>
          </v-list>
        </v-card>
      </v-col>

      <!-- ── Right Column ─────────────────────────────────────────────────── -->
      <v-col cols="12" lg="4">
        <!-- Platform stats pills -->
        <v-card rounded="xl" elevation="0" border class="mb-4">
          <v-card-title class="pa-5 pb-3">
            <div class="text-subtitle-1 font-weight-bold">Platform Health</div>
            <div class="text-caption text-medium-emphasis">
              Live system metrics
            </div>
          </v-card-title>
          <v-card-text class="pa-4 pt-0">
            <v-skeleton-loader
              v-if="store.loading.stats"
              type="list-item-three-line"
            />
            <template v-else>
              <div
                v-for="metric in platformMetrics"
                :key="metric.label"
                class="d-flex align-center justify-space-between mb-3"
              >
                <div class="d-flex align-center gap-3">
                  <v-avatar
                    :color="metric.color"
                    size="32"
                    rounded="lg"
                    variant="tonal"
                  >
                    <v-icon :icon="metric.icon" size="16" />
                  </v-avatar>
                  <div>
                    <div class="text-body-2 font-weight-medium">
                      {{ metric.label }}
                    </div>
                    <div class="text-caption text-grey">{{ metric.sub }}</div>
                  </div>
                </div>
                <div class="text-body-1 font-weight-black">
                  {{ metric.value }}
                </div>
              </div>
            </template>
          </v-card-text>
        </v-card>

        <!-- Recent Tenants -->
        <v-card rounded="xl" elevation="0" border>
          <v-card-title class="pa-5 pb-3">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-subtitle-1 font-weight-bold">
                  Recent Tenants
                </div>
                <div class="text-caption text-medium-emphasis">
                  Newly joined
                </div>
              </div>
              <v-btn
                variant="text"
                color="primary"
                size="small"
                append-icon="mdi-plus"
                @click="$router.push('/tenants/create')"
              >
                Add
              </v-btn>
            </div>
          </v-card-title>
          <v-divider />
          <v-list density="compact" class="pa-2">
            <v-skeleton-loader
              v-if="store.loading.stats"
              v-for="n in 3"
              :key="n"
              type="list-item"
              class="mb-1"
            />
            <template v-else>
              <v-list-item
                v-for="(t, i) in recentTenants"
                :key="t.id"
                rounded="lg"
                class="mb-1 tenant-row"
                @click="$router.push(`/tenants/${t.id}`)"
              >
                <template #prepend>
                  <v-avatar
                    :color="rowColor(i)"
                    size="32"
                    rounded="lg"
                    class="mr-2"
                  >
                    <img
                      v-if="t.logo_url"
                      :src="t.logo_url"
                      style="width: 100%; height: 100%; object-fit: cover"
                    />
                    <v-icon v-else icon="mdi-domain" size="16" color="white" />
                  </v-avatar>
                </template>
                <v-list-item-title class="text-body-2 font-weight-medium">
                  {{ t.name }}
                </v-list-item-title>
                <v-list-item-subtitle class="text-caption">
                  Joined {{ t.created_at }}
                </v-list-item-subtitle>
                <template #append>
                  <v-chip
                    :color="t.is_active ? 'success' : 'error'"
                    size="x-small"
                    variant="tonal"
                    rounded="lg"
                  >
                    {{ t.is_active ? 'Active' : 'Inactive' }}
                  </v-chip>
                </template>
              </v-list-item>
            </template>
          </v-list>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Bottom Row ─────────────────────────────────────────────────────── -->
    <v-row dense class="mt-2">
      <!-- Tenant revenue breakdown bars -->
      <v-col cols="12" sm="6" lg="4">
        <v-card rounded="xl" elevation="0" border height="100%">
          <v-card-title class="pa-5 pb-3">
            <div class="text-subtitle-1 font-weight-bold">
              Revenue by Tenant
            </div>
            <div class="text-caption text-medium-emphasis">
              This {{ selectedPeriod.toLowerCase() }}
            </div>
          </v-card-title>
          <v-card-text class="pa-5 pt-2">
            <v-skeleton-loader
              v-if="store.loading.tenantChart"
              type="list-item-three-line"
            />
            <template v-else>
              <div v-for="(t, i) in tenantBars" :key="t.id" class="mb-4">
                <div class="d-flex justify-space-between mb-1">
                  <span class="text-body-2 font-weight-medium">
                    {{ t.name }}
                  </span>
                  <span class="text-body-2 font-weight-bold">
                    ${{ t.revenue?.toLocaleString() }}
                  </span>
                </div>
                <v-progress-linear
                  :model-value="t.percent"
                  :color="rowColor(i)"
                  rounded
                  height="8"
                  bg-color="grey-lighten-3"
                />
              </div>
              <div
                v-if="!tenantBars.length"
                class="text-center py-4 text-caption text-medium-emphasis"
              >
                No data
              </div>
            </template>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Activity feed -->
      <v-col cols="12" sm="6" lg="5">
        <v-card rounded="xl" elevation="0" border height="100%">
          <v-card-title class="pa-5 pb-3">
            <div class="text-subtitle-1 font-weight-bold">
              Platform Activity
            </div>
            <div class="text-caption text-medium-emphasis">
              Latest events across all tenants
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
            truncate-line="start"
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
                  <v-chip
                    v-if="event.tenant_name"
                    size="x-small"
                    variant="tonal"
                    color="primary"
                    class="mt-1"
                  >
                    {{ event.tenant_name }}
                  </v-chip>
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
            class="text-center py-8 text-caption text-medium-emphasis"
          >
            No recent activity
          </div>
        </v-card>
      </v-col>

      <!-- Quick Actions -->
      <v-col cols="12" lg="3">
        <v-card rounded="xl" elevation="0" border height="100%">
          <v-card-title class="pa-5 pb-3">
            <div class="text-subtitle-1 font-weight-bold">Quick Actions</div>
            <div class="text-caption text-medium-emphasis">Admin shortcuts</div>
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
                  class="quick-action-card pa-3 text-center"
                  hover
                  @click="$router.push(action.route)"
                >
                  <v-icon :icon="action.icon" size="26" class="mb-2" />
                  <div class="text-caption font-weight-semibold">
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
  import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
  import { useAdminDashboardStore } from '@/stores/adminDashboardStore'
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

  const chartCanvas = ref(null)
  let chartInstance = null
  const store = useAdminDashboardStore()

  // ── Period ─────────────────────────────────────────────────────────────────────
  const periods = ['Today', 'Week', 'Month', 'Year']
  const selectedPeriod = ref('Month')
  const chartMode = ref('revenue')

  const today = new Date().toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })

  // ── Data ───────────────────────────────────────────────────────────────────────
  const kpis = computed(() => store.stats?.kpis ?? [])
  const topTenants = computed(() => store.stats?.top_tenants ?? [])
  const recentTenants = computed(() => store.stats?.recent_tenants ?? [])

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

  // ── Platform health metrics ────────────────────────────────────────────────────
  const platformMetrics = computed(() => {
    const kpiMap = Object.fromEntries(
      (store.stats?.kpis ?? []).map(k => [k.label, k.raw])
    )
    return [
      {
        label: 'Active Tenants',
        sub: 'Subscribed',
        icon: 'mdi-domain',
        color: 'primary',
        value: kpiMap['Active Tenants'] ?? '—'
      },
      {
        label: 'Active Branches',
        sub: 'Across all tenants',
        icon: 'mdi-store-outline',
        color: 'success',
        value: kpiMap['Active Branches'] ?? '—'
      },
      {
        label: 'Staff Members',
        sub: 'Active accounts',
        icon: 'mdi-account-group-outline',
        color: 'warning',
        value: kpiMap['Total Staff'] ?? '—'
      },
      {
        label: 'Products',
        sub: 'In catalog',
        icon: 'mdi-package-variant',
        color: 'info',
        value: kpiMap['Total Products'] ?? '—'
      },
      {
        label: 'Orders Today',
        sub: 'Platform-wide',
        icon: 'mdi-receipt-outline',
        color: 'secondary',
        value: store.stats?.total_orders_today ?? '—'
      }
    ]
  })

  // ── Revenue chart ──────────────────────────────────────────────────────────────
  const buildChart = () => {
    if (!chartCanvas.value) return
    if (chartInstance) chartInstance.destroy()

    const labels = store.chart.map(r => r.label)
    const values = store.chart.map(r =>
      chartMode.value === 'revenue' ? r.revenue : r.orders
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
                chartMode.value === 'revenue'
                  ? `$${ctx.parsed.y.toLocaleString()}`
                  : `${ctx.parsed.y} orders`
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
                chartMode.value === 'revenue' ? `$${v.toLocaleString()}` : v
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

  // ── Tenant revenue bars ────────────────────────────────────────────────────────
  const tenantBars = computed(() => {
    const data = store.tenantChart
    const max = Math.max(...data.map(t => t.revenue)) || 1
    return data.slice(0, 6).map(t => ({
      ...t,
      percent: Math.round((t.revenue / max) * 100)
    }))
  })

  // ── Quick actions ──────────────────────────────────────────────────────────────
  const quickActions = [
    {
      label: 'New Tenant',
      icon: 'mdi-domain-plus',
      color: 'primary',
      route: '/tenants/create'
    },
    {
      label: 'All Orders',
      icon: 'mdi-receipt-text-outline',
      color: 'success',
      route: '/orders'
    },
    {
      label: 'All Users',
      icon: 'mdi-account-group-outline',
      color: 'warning',
      route: '/users'
    },
    {
      label: 'Audit Logs',
      icon: 'mdi-clipboard-text-outline',
      color: 'secondary',
      route: '/audit-logs'
    },
    {
      label: 'All Products',
      icon: 'mdi-package-variant',
      color: 'info',
      route: '/products'
    },
    {
      label: 'Settings',
      icon: 'mdi-cog-outline',
      color: 'error',
      route: '/settings'
    }
  ]

  // ── Load & refresh ─────────────────────────────────────────────────────────────
  const onPeriodChange = () =>
    store.fetchAll(selectedPeriod.value.toLowerCase())

  let liveInterval = null
  onMounted(async () => {
    await store.fetchAll(selectedPeriod.value.toLowerCase())
    await nextTick()
    buildChart()
  })
  onUnmounted(() => {
    if (liveInterval) clearInterval(liveInterval)
    if (chartInstance) chartInstance.destroy()
  })
</script>

<style scoped>
  .v-timeline--vertical.v-timeline {
      row-gap: 0px;
      height: 80%;
  }
  .kpi-card--hero {
    background: linear-gradient(135deg, #1867c0 0%, #1565c0 100%) !important;
    box-shadow: 0 8px 24px rgba(24, 103, 192, 0.3) !important;
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
  .tenant-row {
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
  }
  .quick-action-card {
    cursor: pointer;
    transition: transform 0.15s;
  }
  .quick-action-card:hover {
    transform: translateY(-2px);
  }
  .opacity-80 {
    opacity: 0.8;
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
