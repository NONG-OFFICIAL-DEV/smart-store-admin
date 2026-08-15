<template>
  <div class="order-report">
    <!-- ══ Header ══════════════════════════════════════════════════════════ -->
    <custom-title
      icon="mdi-chart-box-outline"
      :title="t('order_report.title')"
      :subtitle="t('order_report.subtitle')"
    >
      <template #right>
        <v-btn
          :color="showFilters ? 'primary' : 'default'"
          :variant="showFilters ? 'flat' : 'tonal'"
          rounded="lg"
          :prepend-icon="
            showFilters ? 'mdi-filter-off-outline' : 'mdi-filter-outline'
          "
          @click="showFilters = !showFilters"
        >
          {{ t('btn.filter') }}
          <!-- badge shows how many filters are active -->
          <v-badge
            v-if="activeFilterCount > 0"
            :content="activeFilterCount"
            color="error"
            floating
          />
        </v-btn>
        <v-btn
          color="success"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-microsoft-excel"
          :loading="exporting"
          :disabled="!stats"
          class="ms-2"
          @click="exportExcel"
        >
          {{ t('btn.export') }}
        </v-btn>
      </template>
    </custom-title>

    <!-- ══ Filter Bar ══════════════════════════════════════════════════════ -->
    <BranchFilterBar
      v-model="filters.branch_id"
      :branches="branchStore.branches?.data || branchStore.branches || []"
      :period="period"
      :date-from="filters.date_from"
      :date-to="filters.date_to"
      @period-change="onPeriodChange"
      @date-change="onDateChange"
    />

    <!-- ══ Tab Navigation ═════════════════════════════════════════════════ -->
    <v-sheet elevation="0" class="mb-4">
      <v-tabs v-model="activeTab" color="primary">
        <v-tab v-for="tab in tabs" :key="tab.key" :value="tab.key">
          <v-icon :icon="tab.icon" size="16" class="mr-1" />
          {{ tab.label }}
          <v-badge
            v-if="tab.badge"
            :content="tab.badge"
            color="primary"
            inline
            class="ml-1"
          />
        </v-tab>
      </v-tabs>
      <v-divider />
    </v-sheet>
    <v-tabs-window v-model="activeTab">
      <!-- ══════════════════════════════════════════════════════════════════ -->
      <!-- TAB 1 · OVERVIEW                                                  -->
      <!-- ══════════════════════════════════════════════════════════════════ -->
      <v-tabs-window-item value="overview">
        <!-- KPI Cards (5) -->
        <v-row dense class="mb-4">
          <v-col v-for="kpi in kpiCards" :key="kpi.label" cols="6" sm="4" md>
            <v-card rounded="xl" border elevation="0" class="kpi-card pa-4">
              <div class="d-flex align-center justify-space-between mb-3">
                <div class="kpi-icon-wrap" :style="`background:${kpi.bg}`">
                  <v-icon :icon="kpi.icon" :color="kpi.color" size="18" />
                </div>
                <v-chip
                  v-if="kpi.change !== '—'"
                  size="x-small"
                  rounded="lg"
                  :color="kpi.changePositive ? 'success' : 'error'"
                  variant="tonal"
                >
                  <v-icon start size="10">
                    {{
                      kpi.changePositive
                        ? 'mdi-trending-up'
                        : 'mdi-trending-down'
                    }}
                  </v-icon>
                  {{ kpi.change }}
                </v-chip>
              </div>
              <div class="kpi-value">{{ kpi.value }}</div>
              <div class="text-caption text-medium-emphasis mt-1">
                {{ kpi.label }}
              </div>
              <div class="text-caption mt-1 kpi-prev">
                {{ t('common.vs') }} {{ periodLabel(previousPeriod) }}:
                {{ kpi.prev }}
              </div>
            </v-card>
          </v-col>
        </v-row>

        <!-- Order Type + Payment split (2-col) -->
        <v-row dense class="mb-4">
          <!-- Order Type Breakdown -->
          <v-col cols="12" md="6">
            <v-card rounded="xl" border elevation="0" class="pa-5 h-100">
              <div class="text-body-1 font-weight-bold mb-1">
                {{ $t('order_report.order_types') }}
              </div>
              <div class="text-caption text-medium-emphasis mb-4">
                Distribution by type
              </div>
              <div
                v-if="orderTypeStats.length"
                style="display: flex; flex-direction: column; gap: 10px"
              >
                <div v-for="(ot, i) in orderTypeStats" :key="ot.type">
                  <div class="d-flex align-center mb-1" style="gap: 8px">
                    <v-avatar
                      :color="ot.color"
                      size="26"
                      rounded="md"
                      variant="tonal"
                    >
                      <v-icon :icon="ot.icon" size="13" />
                    </v-avatar>
                    <span class="text-body-2" style="flex: 1">
                      {{ ot.label }}
                    </span>
                    <span class="text-caption font-weight-bold">
                      {{ ot.count }}
                    </span>
                    <span
                      class="text-caption text-medium-emphasis"
                      style="min-width: 34px; text-align: right"
                    >
                      {{ ot.pct }}%
                    </span>
                  </div>
                  <div class="product-bar-track">
                    <div
                      class="product-bar-fill"
                      :style="`width:${ot.pct}%;background:${otColors[i % otColors.length]}`"
                    />
                  </div>
                </div>
              </div>
              <div
                v-else
                class="d-flex flex-column align-center justify-center"
                style="height: 160px; gap: 6px"
              >
                <v-icon
                  icon="mdi-chart-donut"
                  size="32"
                  color="grey-lighten-1"
                />
                <span class="text-caption text-disabled">No data</span>
              </div>
            </v-card>
          </v-col>

          <!-- Payment Method donut -->
          <v-col cols="12" md="6">
            <v-card
              rounded="xl"
              border
              elevation="0"
              class="pa-5"
              style="height: 100%"
            >
              <div class="text-body-1 font-weight-bold mb-1">
                {{ t('order_report.payment_method') }}
              </div>
              <div class="text-caption text-medium-emphasis mb-3">
                {{ t('order_report.split_by_revenue') }}
              </div>
              <div class="d-flex align-center" style="gap: 16px">
                <div
                  style="
                    width: 130px;
                    height: 130px;
                    flex-shrink: 0;
                    position: relative;
                  "
                >
                  <canvas ref="payDonutRef" />
                  <div class="donut-center-label">
                    <div
                      class="text-caption font-weight-bold"
                      style="line-height: 1.2"
                    >
                      {{ paymentStats.length }}
                    </div>
                    <div style="font-size: 9px; opacity: 0.5">methods</div>
                  </div>
                </div>
                <div style="flex: 1">
                  <div
                    v-for="(item, i) in paymentStats"
                    :key="item.label"
                    class="d-flex align-center mb-2"
                    style="gap: 8px"
                  >
                    <span
                      class="legend-dot"
                      :style="`background:${payColors[i % payColors.length]}`"
                    />
                    <span
                      class="text-caption text-medium-emphasis"
                      style="flex: 1"
                    >
                      {{ item.label }}
                    </span>
                    <span class="text-caption font-weight-bold">
                      {{ format(item.revenue) }}
                    </span>
                    <v-chip
                      size="x-small"
                      variant="tonal"
                      :color="payChipColors[i % payChipColors.length]"
                      rounded="lg"
                    >
                      {{ item.pct }}%
                    </v-chip>
                  </div>
                </div>
              </div>
            </v-card>
          </v-col>
        </v-row>

        <!-- Quick insight strip -->
        <v-row dense class="mb-2">
          <v-col cols="12">
            <v-card rounded="xl" border elevation="0" class="pa-4">
              <div class="d-flex align-center flex-wrap" style="gap: 12px">
                <div class="insight-pill" v-if="peakHour">
                  <v-icon icon="mdi-fire" color="deep-orange" size="16" />
                  <span>
                    {{ t('order_report.insights.peak_hour') }}
                    <strong>{{ peakHour.label }}</strong>
                    ·
                    {{
                      t('order_report.insights.orders_count', {
                        count: peakHour.count
                      })
                    }}
                  </span>
                </div>

                <div class="insight-pill" v-if="topProducts[0]">
                  <v-icon
                    icon="mdi-trophy-outline"
                    color="amber-darken-2"
                    size="16"
                  />
                  <span>
                    {{ t('order_report.insights.top_item') }}
                    <strong>{{ topProducts[0].name }}</strong>
                  </span>
                </div>

                <div class="insight-pill" v-if="stats">
                  <v-icon icon="mdi-cash-multiple" color="success" size="16" />
                  <span>
                    {{ t('order_report.insights.total_revenue') }}
                    <strong>{{ format(stats.total_revenue ?? 0) }}</strong>
                    ·
                    {{
                      t('order_report.insights.orders_count', {
                        count: stats.total_orders
                      })
                    }}
                  </span>
                </div>
              </div>
            </v-card>
          </v-col>
        </v-row>
      </v-tabs-window-item>

      <!-- ══════════════════════════════════════════════════════════════════ -->
      <!-- TAB 2 · CHARTS                                                    -->
      <!-- ══════════════════════════════════════════════════════════════════ -->
      <v-tabs-window-item value="charts">
        <!-- Revenue / Orders over time (full width) -->
        <v-row dense class="mb-4">
          <v-col cols="12">
            <v-card rounded="xl" border elevation="0" class="pa-5">
              <div class="d-flex align-center justify-space-between mb-4">
                <div>
                  <div class="text-body-1 font-weight-bold">
                    {{ t('order_report.revenue_over_time') }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ periodLabel(period) }}
                  </div>
                </div>
                <div class="d-flex align-center" style="gap: 10px">
                  <div v-if="stats" class="d-flex" style="gap: 6px">
                    <v-chip
                      size="x-small"
                      variant="tonal"
                      color="primary"
                      rounded="lg"
                    >
                      <v-icon start size="10">mdi-receipt-text-outline</v-icon>
                      {{ stats.total_orders }} orders
                    </v-chip>
                    <v-chip
                      size="x-small"
                      variant="tonal"
                      color="success"
                      rounded="lg"
                    >
                      {{ format(stats.total_revenue) }}
                    </v-chip>
                  </div>
                  <v-btn-toggle
                    v-model="chartMode"
                    mandatory
                    density="compact"
                    rounded="lg"
                    variant="outlined"
                    color="primary"
                    size="x-small"
                  >
                    <v-btn value="revenue" size="x-small">
                      {{ t('order_report.state.revenue') }}
                    </v-btn>
                    <v-btn value="orders" size="x-small">
                      {{ t('order_report.state.orders') }}
                    </v-btn>
                  </v-btn-toggle>
                </div>
              </div>
              <div style="height: 260px"><canvas ref="lineChartRef" /></div>
            </v-card>
          </v-col>
        </v-row>

        <!-- Hourly traffic (full width) -->
        <v-row dense class="mb-4">
          <v-col cols="12">
            <v-card rounded="xl" border elevation="0" class="pa-5">
              <div class="d-flex align-center justify-space-between mb-2">
                <div>
                  <div class="text-body-1 font-weight-bold">
                    {{ t('order_report.sales_by_time') }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ t('order_report.orders_per_hour') }}
                  </div>
                </div>
                <div class="d-flex align-center" style="gap: 8px">
                  <v-chip
                    v-if="peakHour"
                    size="x-small"
                    color="deep-orange"
                    variant="tonal"
                    rounded="lg"
                  >
                    <v-icon start size="10">mdi-fire</v-icon>
                    Peak: {{ peakHour.label }} · {{ peakHour.count }} orders
                  </v-chip>
                  <v-btn-toggle
                    v-model="hourlyMode"
                    mandatory
                    density="compact"
                    rounded="lg"
                    variant="outlined"
                    color="primary"
                    size="x-small"
                  >
                    <v-btn value="hourly" size="x-small">
                      {{ t('order_report.hourly') }}
                    </v-btn>
                    <v-btn value="daily" size="x-small">
                      {{ t('order_report.daily') }}
                    </v-btn>
                    <v-btn value="monthly" size="x-small">
                      {{ t('order_report.monthly') }}
                    </v-btn>
                  </v-btn-toggle>
                </div>
              </div>
              <div style="height: 240px"><canvas ref="hourlyChartRef" /></div>
            </v-card>
          </v-col>
        </v-row>
      </v-tabs-window-item>

      <!-- ══════════════════════════════════════════════════════════════════ -->
      <!-- TAB 3 · PRODUCTS                                                  -->
      <!-- ══════════════════════════════════════════════════════════════════ -->
      <v-tabs-window-item value="products">
        <v-row dense class="mb-4">
          <v-col cols="12">
            <v-card rounded="xl" border elevation="0" class="pa-5">
              <div class="d-flex align-center justify-space-between mb-5">
                <div>
                  <div class="text-body-1 font-weight-bold">
                    {{ t('order_report.top_products') }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ t('order_report.top_products_subtitle') }}
                  </div>
                </div>
                <v-btn-toggle
                  v-model="productMode"
                  mandatory
                  density="compact"
                  rounded="lg"
                  variant="outlined"
                  color="primary"
                  size="x-small"
                >
                  <v-btn value="qty" size="x-small">
                    {{ t('order_report.by_qty') }}
                  </v-btn>
                  <v-btn value="revenue" size="x-small">
                    {{ t('order_report.state.revenue') }}
                  </v-btn>
                </v-btn-toggle>
              </div>

              <div v-if="topProducts.length > 0">
                <!-- Top 3 podium -->
                <v-row dense class="mb-4">
                  <v-col
                    v-for="(product, i) in topProducts.slice(0, 3)"
                    :key="'top-' + product.name"
                    cols="12"
                    sm="4"
                  >
                    <v-card
                      rounded="xl"
                      elevation="0"
                      :class="[
                        'top-product-podium pa-4',
                        ['podium-gold', 'podium-silver', 'podium-bronze'][i]
                      ]"
                    >
                      <div class="d-flex align-center mb-2 podium-header">
                        <span class="podium-medal">
                          {{ ['🥇', '🥈', '🥉'][i] }}
                        </span>
                        <span class="text-body-2 font-weight-bold podium-name">
                          {{ product.name }}
                        </span>
                      </div>
                      <div class="text-h6 font-weight-bold">
                        {{
                          productMode === 'qty'
                            ? product.qty
                            : format(product.revenue)
                        }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{
                          productMode === 'qty'
                            ? t('order_report.sold')
                            : t('order_report.revenue')
                        }}
                      </div>
                    </v-card>
                  </v-col>
                </v-row>

                <!-- Remaining list -->
                <v-row dense>
                  <v-col
                    v-for="(product, i) in topProducts.slice(3)"
                    :key="product.name"
                    cols="12"
                    sm="6"
                    md="4"
                  >
                    <div class="d-flex align-center mb-1" style="gap: 8px">
                      <span
                        class="text-caption font-weight-bold"
                        style="width: 20px; text-align: right; opacity: 0.35"
                      >
                        {{ i + 4 }}
                      </span>
                      <span
                        class="text-body-2"
                        style="
                          flex: 1;
                          min-width: 0;
                          overflow: hidden;
                          text-overflow: ellipsis;
                          white-space: nowrap;
                        "
                      >
                        {{ product.name }}
                      </span>
                      <span class="text-caption text-medium-emphasis">
                        {{
                          productMode === 'qty'
                            ? product.qty + ' ' + t('order_report.sold')
                            : format(product.revenue)
                        }}
                      </span>
                    </div>
                    <div class="product-bar-track">
                      <div
                        class="product-bar-fill"
                        :style="`width:${product.pct}%;background:${productBarColors[(i + 3) % productBarColors.length]}`"
                      />
                    </div>
                  </v-col>
                </v-row>
              </div>

              <div
                v-else
                class="d-flex flex-column align-center justify-center pa-10"
                style="gap: 8px"
              >
                <v-icon
                  icon="mdi-receipt-text-remove-outline"
                  size="44"
                  color="grey-lighten-1"
                />
                <div class="text-body-2 text-medium-emphasis">
                  {{$t('products.empty')}}
                </div>
                <div class="text-caption text-disabled">
                  No orders were placed during this period
                </div>
              </div>
            </v-card>
          </v-col>
        </v-row>
      </v-tabs-window-item>

      <!-- ══════════════════════════════════════════════════════════════════ -->
      <!-- TAB 4 · ORDERS                                                    -->
      <!-- ══════════════════════════════════════════════════════════════════ -->
      <v-tabs-window-item value="orders">
        <!-- ── Filters ──────────────────────────────────────────────────────── -->
        <v-expand-transition>
          <v-card v-if="showFilters" rounded="xl" elevation="0" class="mb-4">
            <v-card-text>
              <v-row dense align="center">
                <v-col cols="12" sm="4">
                  <v-text-field
                    v-model="filters.search"
                    :placeholder="
                      t('order_report.search_placeholder') ||
                      'Search order #, customer…'
                    "
                    variant="outlined"
                    rounded="lg"
                    hide-details
                    clearable
                    prepend-inner-icon="mdi-magnify"
                    @update:model-value="onSearch"
                    @keyup.enter="onFilterChange"
                  />
                </v-col>
                <v-col cols="12" sm="4" md="3">
                  <v-select
                    v-model="filters.order_type"
                    :items="orderTypeOptions"
                    item-title="label"
                    item-value="value"
                    :placeholder="t('order_report.filter.type') || 'Order Type'"
                    variant="outlined"
                    rounded="lg"
                    hide-details
                    clearable
                    @update:model-value="loadOrders"
                  />
                </v-col>
                <v-col cols="12" sm="4" md="3">
                  <v-select
                    v-model="filters.status"
                    :items="statusOptions"
                    item-title="label"
                    item-value="value"
                    :placeholder="t('order_report.filter.status') || 'Status'"
                    variant="outlined"
                    rounded="lg"
                    hide-details
                    clearable
                    @update:model-value="loadOrders"
                  />
                </v-col>
              </v-row>
            </v-card-text>
            <v-card-actions class="px-4">
              <v-spacer />
              <v-btn
                v-if="hasActiveFilters"
                rounded="lg"
                variant="tonal"
                color="error"
                prepend-icon="mdi-close"
                @click="resetFilters"
              >
                {{ t('btn.reset') }}
              </v-btn>
              <v-btn
                class="bg-primary"
                rounded="lg"
                prepend-icon="mdi-magnify"
                @click="onFilterChange"
              >
                {{ t('btn.search') }}
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-expand-transition>

        <v-card rounded="xl" border elevation="0" class="mb-4">
          <!-- Result count -->
          <div
            v-if="pagination"
            class="pa-3 d-flex justify-end filter-bar"
          >
            <v-chip size="small" variant="tonal" color="primary" rounded="lg">
              <v-icon start size="12">mdi-format-list-bulleted</v-icon>
              {{ pagination.total }} total
            </v-chip>
          </div>

          <!-- Table -->
          <v-data-table-server
            :headers="headers"
            :items="orders"
            :items-length="pagination?.total ?? 0"
            :loading="tableLoading"
            :items-per-page="filters.per_page"
            :page="filters.page"
            item-value="id"
            class="order-table"
            @update:page="
              p => {
                filters.page = p
                loadOrders()
              }
            "
            @update:items-per-page="
              p => {
                filters.per_page = p
                filters.page = 1
                loadOrders()
              }
            "
          >
            <template #item.order_number="{ item }">
              <div class="d-flex align-center py-1" style="gap: 8px">
                <v-avatar
                  :color="typeColor(item.order_type)"
                  size="30"
                  rounded="md"
                  variant="tonal"
                >
                  <v-icon :icon="typeIcon(item.order_type)" size="14" />
                </v-avatar>
                <div>
                  <div class="text-body-2 font-weight-bold">
                    {{ item.order_number }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ item.branch?.name }}
                  </div>
                </div>
              </div>
            </template>

            <template #item.customer="{ item }">
              <div v-if="item.customer">
                <div class="text-body-2">{{ item.customer.name }}</div>
                <div class="text-caption text-medium-emphasis">
                  {{ item.customer.phone }}
                </div>
              </div>
              <span v-else class="text-caption text-medium-emphasis">
                Walk-in
              </span>
            </template>

            <template #item.items_count="{ item }">
              <v-chip size="x-small" variant="tonal" rounded="lg">
                {{ item.items?.length ?? 0 }}
                {{ t('order_report.headers.items') }}
              </v-chip>
            </template>

            <template #item.total_amount="{ item }">
              <span class="font-weight-bold text-body-2">
                {{ format(item.total_amount) }}
              </span>
            </template>

            <template #item.payment_method="{ item }">
              <v-chip
                v-if="getPaymentMethod(item)"
                size="small"
                rounded="lg"
                :color="payColor(getPaymentMethod(item))"
              >
                {{ payLabel(getPaymentMethod(item)) }}
              </v-chip>
              <span v-else class="text-caption text-medium-emphasis">—</span>
            </template>

            <template #item.status="{ item }">
              <v-chip
                size="small"
                rounded="lg"
                variant="tonal"
                :color="statusColor(item.status)"
              >
                <v-icon start size="10">{{ statusIcon(item.status) }}</v-icon>
                {{ item.status }}
              </v-chip>
            </template>

            <template #item.created_at="{ item }">
              <div class="text-body-2">{{ fmtDate(item.created_at) }}</div>
              <div class="text-caption text-medium-emphasis">
                {{ fmtTime(item.created_at) }}
              </div>
            </template>

            <template #item.actions="{ item }">
              <v-btn
                icon="mdi-eye-outline"
                size="small"
                variant="text"
                color="primary"
                @click="viewOrder(item)"
              />
            </template>
          </v-data-table-server>
        </v-card>
      </v-tabs-window-item>
    </v-tabs-window>
    <OrderDetailDialog v-model="detailDialog" :order-id="selectedOrderId" />
  </div>
</template>

<script setup>
  import { ref, computed, onMounted, watch, nextTick } from 'vue'
  import { useOrderStore } from '@/stores/orderStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useI18n } from 'vue-i18n'
  import { useAppUtils } from '@/composables/useAppUtils'
  import OrderDetailDialog from '@/components/orders/OrderDetailDialog.vue'
  import BranchFilterBar from '@/components/common/BranchFilterBar.vue'

  import {
    Chart,
    LineElement,
    BarElement,
    ArcElement,
    PointElement,
    LineController,
    BarController,
    DoughnutController,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
    Filler
  } from 'chart.js'

  Chart.register(
    LineElement,
    BarElement,
    ArcElement,
    PointElement,
    LineController,
    BarController,
    DoughnutController,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
    Filler
  )

  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import { useDate } from '@/composables/useDate'
  const { format, currencySymbol } = useCurrency()
  const { formatShortDate: fmtDate, formatTime: fmtTime } = useDate()

  const { notif } = useAppUtils()
  const branchStore = useBranchStore()
  const orderStore = useOrderStore()
  const { t } = useI18n()

  // ── Canvas refs ────────────────────────────────────────────────────────────────
  const lineChartRef = ref(null)
  const hourlyChartRef = ref(null)
  const payDonutRef = ref(null)
  let lineChart = null,
    hourlyChart = null,
    payDonut = null

  // ── Active tab ─────────────────────────────────────────────────────────────────
  const activeTab = ref('overview')
  const showFilters = ref(false)

  // ── State ──────────────────────────────────────────────────────────────────────
  const period = ref('today')
  const chartMode = ref('revenue')
  const hourlyMode = ref('hourly')
  const productMode = ref('qty')
  const exporting = ref(false)
  const tableLoading = ref(false)

  const stats = ref(null)
  const prevStats = ref(null)
  const chartData = ref([])
  const orders = ref([])
  const pagination = ref(null)
  const paymentStats = ref([])
  const topProductsRaw = ref([])
  const hourlyData = ref([])
  const orderTypeStatsRaw = ref([])

  const detailDialog = ref(false)
  const selectedOrderId = ref(null)

  const filters = ref({
    branch_id: null,
    search: '',
    status: null,
    order_type: null,
    date_from: null,
    date_to: null,
    per_page: 10,
    page: 1
  })

  // ── Colors ─────────────────────────────────────────────────────────────────────
  const payColors = ['#1D9E75', '#378ADD', '#BA7517', '#D85A30', '#7F77DD']
  const payChipColors = ['success', 'primary', 'warning', 'error', 'purple']
  const productBarColors = [
    '#378ADD',
    '#1D9E75',
    '#BA7517',
    '#D85A30',
    '#7F77DD',
    '#888780'
  ]
  const otColors = ['#378ADD', '#1D9E75', '#BA7517', '#D85A30']

  // ── Tabs definition ────────────────────────────────────────────────────────────
  const tabs = computed(() => [
    {
      key: 'overview',
      label: t('order_report.tabs.overview'),
      icon: 'mdi-view-dashboard-outline'
    },
    {
      key: 'charts',
      label: t('order_report.tabs.charts'),
      icon: 'mdi-chart-areaspline'
    },
    {
      key: 'products',
      label: t('order_report.tabs.products'),
      icon: 'mdi-food-outline',
      badge: topProductsRaw.value.length || null
    },
    {
      key: 'orders',
      label: t('order_report.tabs.orders'),
      icon: 'mdi-receipt-text-outline',
      badge: pagination.value?.total || null
    }
  ])

  // ── Period helpers ─────────────────────────────────────────────────────────────
  const previousPeriod = computed(
    () =>
      ({
        today: 'yesterday',
        yesterday: 'day_before',
        week: 'last_week',
        month: 'last_month',
        last_month: 'month_before',
        custom: 'prev_custom'
      })[period.value] ?? 'yesterday'
  )

  const periodLabel = p =>
    ({
      today: t('common.today'),
      yesterday: t('common.yesterday'),
      week: t('common.this_week'),
      month: t('common.this_month'),
      last_month: t('common.last_month'),
      day_before: t('common.day_before'),
      last_week: t('common.last_week'),
      month_before: t('common.month_before'),
      custom: t('common.custom'),
      prev_custom: t('common.prev_custom')
    })[p] ?? p

  const periodDates = p => {
    const today = new Date()
    const fmt = d => d.toISOString().slice(0, 10)
    const add = (d, n) => {
      const x = new Date(d)
      x.setDate(x.getDate() + n)
      return x
    }
    switch (p) {
      case 'today':
        return { from: fmt(today), to: fmt(today) }
      case 'yesterday': {
        const y = add(today, -1)
        return { from: fmt(y), to: fmt(y) }
      }
      case 'week': {
        const m = new Date(today)
        m.setDate(today.getDate() - today.getDay() + 1)
        return { from: fmt(m), to: fmt(today) }
      }
      case 'month': {
        const f = new Date(today.getFullYear(), today.getMonth(), 1)
        return { from: fmt(f), to: fmt(today) }
      }
      case 'last_month': {
        const f = new Date(today.getFullYear(), today.getMonth() - 1, 1)
        const l = new Date(today.getFullYear(), today.getMonth(), 0)
        return { from: fmt(f), to: fmt(l) }
      }
      case 'custom':
        return { from: filters.value.date_from, to: filters.value.date_to }
      default:
        return { from: fmt(today), to: fmt(today) }
    }
  }

  const previousPeriodDates = computed(() => {
    const cur = periodDates(period.value)
    if (!cur.from || !cur.to) return cur
    const from = new Date(cur.from),
      to = new Date(cur.to)
    const days = Math.round((to - from) / 86400000) + 1
    const pFrom = new Date(from)
    pFrom.setDate(pFrom.getDate() - days)
    const pTo = new Date(to)
    pTo.setDate(pTo.getDate() - days)
    return {
      from: pFrom.toISOString().slice(0, 10),
      to: pTo.toISOString().slice(0, 10)
    }
  })

  // ── KPI cards ──────────────────────────────────────────────────────────────────
  const kpiCards = computed(() => {
    const s = stats.value,
      ps = prevStats.value
    if (!s) return []
    const pct = (a, b) => (b ? (((a - b) / b) * 100).toFixed(1) + '%' : '—')
    const up = (a, b) => a >= b
    const avg = s.total_orders ? s.total_revenue / s.total_orders : 0
    const avgPrev = ps?.total_orders ? ps.total_revenue / ps.total_orders : 0

    return [
      {
        label: t('order_report.state.revenue'),
        value: format(s.total_revenue ?? 0),
        prev: format(ps?.total_revenue ?? 0),
        change: pct(s.total_revenue, ps?.total_revenue),
        changePositive: up(s.total_revenue, ps?.total_revenue),
        icon: 'mdi-cash-multiple',
        color: 'success',
        bg: '#e8f5e9'
      },
      {
        label: t('order_report.state.orders'),
        value: s.total_orders ?? 0,
        prev: ps?.total_orders ?? 0,
        change: pct(s.total_orders, ps?.total_orders),
        changePositive: up(s.total_orders, ps?.total_orders),
        icon: 'mdi-receipt-text-outline',
        color: 'primary',
        bg: '#e3f2fd'
      },
      {
        label: t('order_report.state.avg_order_value'),
        value: format(avg),
        prev: format(avgPrev),
        change: pct(avg, avgPrev),
        changePositive: up(avg, avgPrev),
        icon: 'mdi-tag-outline',
        color: 'info',
        bg: '#e1f5fe'
      }
    ]
  })

  // ── Computed helpers ───────────────────────────────────────────────────────────
  const topProducts = computed(() => {
    const sorted = [...topProductsRaw.value]
      .sort((a, b) =>
        productMode.value === 'qty' ? b.qty - a.qty : b.revenue - a.revenue
      )
      .slice(0, 12)
    const max =
      sorted[0]?.[productMode.value === 'qty' ? 'qty' : 'revenue'] || 1
    return sorted.map(p => ({
      ...p,
      pct: Math.round(
        ((productMode.value === 'qty' ? p.qty : p.revenue) / max) * 100
      )
    }))
  })

  const orderTypeStats = computed(() => {
    const total = orderTypeStatsRaw.value.reduce((s, x) => s + x.count, 0) || 1
    return orderTypeStatsRaw.value.map(ot => ({
      ...ot,
      pct: Math.round((ot.count / total) * 100)
    }))
  })

  const peakHour = computed(() => {
    if (!hourlyData.value.length) return null
    return hourlyData.value.reduce(
      (a, b) => (b.count > a.count ? b : a),
      hourlyData.value[0]
    )
  })

  // ── Options ────────────────────────────────────────────────────────────────────
  const orderTypeOptions = [
    { value: null, label: 'All Types' },
    { value: 'dine_in', label: 'Dine In' },
    { value: 'takeaway', label: 'Takeaway' },
    { value: 'delivery', label: 'Delivery' },
    { value: 'walk_in', label: 'Walk In' }
  ]
  const statusOptions = [
    { value: null, label: 'All Status' },
    { value: 'pending', label: 'Pending' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'preparing', label: 'Preparing' },
    { value: 'ready', label: 'Ready' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' }
  ]
  const headers = [
    {
      title: t('order_report.headers.order_number'),
      key: 'order_number',
      sortable: false
    },
    {
      title: t('order_report.headers.customer'),
      key: 'customer',
      sortable: false
    },
    {
      title: t('order_report.headers.items'),
      key: 'items_count',
      sortable: false
    },
    {
      title: t('order_report.headers.total'),
      key: 'total_amount',
      sortable: true
    },
    {
      title: t('order_report.headers.payment'),
      key: 'payment_method',
      sortable: false
    },
    {
      title: t('order_report.headers.status') || 'Status',
      key: 'status',
      sortable: false
    },
    {
      title: t('order_report.headers.date'),
      key: 'created_at',
      sortable: true
    },
    { title: '', key: 'actions', sortable: false, width: '48' }
  ]

  // ── Helpers ────────────────────────────────────────────────────────────────────
  const statusColor = s =>
    ({
      pending: 'warning',
      confirmed: 'info',
      preparing: 'info',
      ready: 'success',
      completed: 'success',
      cancelled: 'error'
    })[s] ?? 'grey'
  const statusIcon = s =>
    ({
      pending: 'mdi-clock-outline',
      confirmed: 'mdi-check',
      preparing: 'mdi-chef-hat',
      ready: 'mdi-bell-outline',
      completed: 'mdi-check-circle-outline',
      cancelled: 'mdi-close-circle-outline'
    })[s] ?? 'mdi-circle-outline'
  const typeColor = v =>
    ({
      dine_in: 'primary',
      takeaway: 'info',
      delivery: 'warning',
      walk_in: 'success'
    })[v] ?? 'grey'
  const typeIcon = v =>
    ({
      dine_in: 'mdi-silverware',
      takeaway: 'mdi-bag-personal',
      delivery: 'mdi-moped',
      walk_in: 'mdi-walk'
    })[v] ?? 'mdi-cart'

  const getPaymentMethod = o =>
    o.payments?.[0]?.payment_method ?? o.payment_method ?? null
  const payLabel = r =>
    ({
      cash: 'Cash',
      card: 'Card',
      qr_code: 'QR',
      qr: 'QR',
      online: 'Transfer',
      transfer: 'Transfer'
    })[r] ??
    r?.replace('_', ' ') ??
    '—'
  const payColor = r =>
    ({
      cash: 'success',
      card: 'primary',
      qr_code: 'info',
      qr: 'info',
      online: 'purple',
      transfer: 'purple',
      store_credit: 'warning',
      credit_term: 'error'
    })[r] ?? 'grey'

  // ── Data loading ───────────────────────────────────────────────────────────────
  const onPeriodChange = val => {
    period.value = val
    if (val !== 'custom') {
      const d = periodDates(val)
      filters.value.date_from = d.from
      filters.value.date_to = d.to
      filters.value.page = 1
      loadAll()
    }
  }
  const onDateChange = ({ from, to }) => {
    filters.value.date_from = from
    filters.value.date_to = to
    period.value = 'custom'
    if (from && to) loadAll()
  }
  const loadAll = async () => {
    await Promise.all([
      loadStats(),
      loadOrders(),
      loadChartData(),
      branchStore.fetchBranches()
    ])
  }
  const loadStats = async () => {
    try {
      const dates = periodDates(period.value)
      const prev = previousPeriodDates.value
      const [cur, pre] = await Promise.all([
        orderStore.getAllOrdersReport({
          date_from: dates.from,
          date_to: dates.to,
          per_page: 1
        }),
        orderStore.getAllOrdersReport({
          date_from: prev.from,
          date_to: prev.to,
          per_page: 1
        })
      ])
      stats.value = cur.data.stats
      prevStats.value = pre.data.stats
    } catch (e) {
      console.error(e)
    }
  }
  const loadOrders = async () => {
    tableLoading.value = true
    try {
      const dates = periodDates(period.value)
      const res = await orderStore.getAllOrdersReport({
        ...filters.value,
        date_from: filters.value.date_from ?? dates.from,
        date_to: filters.value.date_to ?? dates.to
      })
      orders.value = res.data.data.data
      pagination.value = {
        current_page: res.data.data.current_page,
        last_page: res.data.data.last_page,
        per_page: res.data.data.per_page,
        total: res.data.data.total
      }
    } catch (e) {
      console.error(e)
    } finally {
      tableLoading.value = false
    }
  }
  const loadChartData = async () => {
    try {
      const dates = periodDates(period.value)
      const res = await orderStore.getAllOrdersReport({
        date_from: dates.from,
        date_to: dates.to,
        per_page: 9999,
        branch_id: filters.value.branch_id
      })
      const all = res.data.data.data
      buildChartData(all, dates.from, dates.to)
      buildPaymentStats(all)
      buildTopProducts(all)
      buildHourlyData(all)
      buildOrderTypeStats(all)
    } catch (e) {
      console.error(e)
    }
  }

  // ── Build helpers ──────────────────────────────────────────────────────────────
  const buildChartData = (all, from, to) => {
    const map = {}
    const cur = new Date(from),
      end = new Date(to)
    while (cur <= end) {
      const k = cur.toISOString().slice(0, 10)
      map[k] = { label: k, revenue: 0, orders: 0 }
      cur.setDate(cur.getDate() + 1)
    }
    for (const o of all) {
      const k = o.created_at?.slice(0, 10)
      if (map[k] && o.status !== 'cancelled') {
        map[k].revenue += parseFloat(o.total_amount ?? 0)
        map[k].orders++
      }
    }
    chartData.value = Object.values(map)
    nextTick(() => drawLineChart())
  }
  const buildPaymentStats = all => {
    const map = {}
    for (const o of all) {
      if (o.status === 'cancelled') continue
      const raw =
        o.payments?.[0]?.payment_method ?? o.payment_method ?? 'unknown'
      const label =
        {
          cash: 'Cash',
          card: 'Card',
          qr_code: 'QR',
          online: 'Transfer',
          qr: 'QR',
          transfer: 'Transfer'
        }[raw] ?? raw
      if (!map[label]) map[label] = 0
      map[label] += parseFloat(o.total_amount ?? 0)
    }
    const total = Object.values(map).reduce((a, b) => a + b, 0)
    paymentStats.value = Object.entries(map)
      .sort((a, b) => b[1] - a[1])
      .map(([label, revenue]) => ({
        label,
        revenue,
        pct: total ? Math.round((revenue / total) * 100) : 0
      }))
    nextTick(() => drawPayDonut())
  }
  const buildTopProducts = all => {
    const map = {}
    for (const o of all) {
      if (o.status === 'cancelled') continue
      for (const item of o.items ?? []) {
        const name = item.product_name ?? item.name ?? 'Unknown'
        if (!map[name]) map[name] = { name, qty: 0, revenue: 0 }
        map[name].qty += item.quantity ?? 1
        map[name].revenue += parseFloat(item.total_price ?? 0)
      }
    }
    topProductsRaw.value = Object.values(map)
  }
  const buildOrderTypeStats = all => {
    const m = { dine_in: 0, takeaway: 0, delivery: 0, walk_in: 0 }
    for (const o of all) {
      if (o.status === 'cancelled') continue
      const tp = o.order_type ?? 'walk_in'
      if (m[tp] !== undefined) m[tp]++
    }
    const labels = {
      dine_in: 'Dine In',
      takeaway: 'Takeaway',
      delivery: 'Delivery',
      walk_in: 'Walk In'
    }
    const icons = {
      dine_in: 'mdi-silverware',
      takeaway: 'mdi-bag-personal',
      delivery: 'mdi-moped',
      walk_in: 'mdi-walk'
    }
    const colors = {
      dine_in: 'primary',
      takeaway: 'info',
      delivery: 'warning',
      walk_in: 'success'
    }
    orderTypeStatsRaw.value = Object.entries(m)
      .filter(([, v]) => v > 0)
      .sort((a, b) => b[1] - a[1])
      .map(([type, count]) => ({
        type,
        count,
        label: labels[type],
        icon: icons[type],
        color: colors[type]
      }))
  }
  const buildHourlyData = all => {
    if (hourlyMode.value === 'hourly') {
      const m = {}
      for (let h = 0; h < 24; h++) m[h] = 0
      for (const o of all) {
        if (o.status === 'cancelled') continue
        m[new Date(o.created_at).getHours()]++
      }
      hourlyData.value = Object.entries(m)
        .filter(([h]) => Number(h) >= 6 && Number(h) <= 23)
        .map(([h, count]) => ({ label: `${h}:00`, count }))
    } else if (hourlyMode.value === 'daily') {
      const m = {}
      for (const o of all) {
        if (o.status === 'cancelled') continue
        const k = o.created_at?.slice(0, 10)
        if (!m[k]) m[k] = 0
        m[k]++
      }
      hourlyData.value = Object.entries(m)
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([l, count]) => ({
          label: new Date(l).toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'short'
          }),
          count
        }))
    } else {
      const m = {}
      for (const o of all) {
        if (o.status === 'cancelled') continue
        const d = new Date(o.created_at)
        const k = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
        if (!m[k]) m[k] = 0
        m[k]++
      }
      hourlyData.value = Object.entries(m)
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([l, count]) => ({
          label: new Date(l + '-01').toLocaleDateString('en-GB', {
            month: 'short',
            year: 'numeric'
          }),
          count
        }))
    }
    nextTick(() => drawHourlyChart())
  }

  // ── Chart drawing ──────────────────────────────────────────────────────────────
  const drawLineChart = () => {
    if (!lineChartRef.value) return
    if (lineChart) lineChart.destroy()
    const labels = chartData.value.map(d =>
      new Date(d.label).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short'
      })
    )
    const values =
      chartMode.value === 'revenue'
        ? chartData.value.map(d => d.revenue)
        : chartData.value.map(d => d.orders)
    lineChart = new Chart(lineChartRef.value, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label:
              chartMode.value === 'revenue'
                ? `Revenue (${currencySymbol()})`
                : 'Orders',
            data: values,
            borderColor: '#378ADD',
            backgroundColor: values.map((v, i) =>
              i === values.indexOf(Math.max(...values))
                ? '#378ADD'
                : 'rgba(55,138,221,0.15)'
            ),
            fill: true,
            tension: 0.4,
            borderRadius: 6,
            borderSkipped: false,
            borderWidth: 0
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            mode: 'index',
            intersect: false,
            callbacks: {
              label: ctx =>
                chartMode.value === 'revenue'
                  ? format(ctx.parsed.y)
                  : ctx.parsed.y + ' orders'
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(0,0,0,0.04)' },
            ticks: {
              callback: v => (chartMode.value === 'revenue' ? format(v) : v),
              font: { size: 11 }
            }
          },
          x: {
            grid: { display: false },
            ticks: { maxTicksLimit: 10, font: { size: 11 } }
          }
        }
      }
    })
  }
  const drawHourlyChart = () => {
    if (!hourlyChartRef.value) return
    if (hourlyChart) hourlyChart.destroy()
    const data = hourlyData.value
    const counts = data.map(d => d.count)
    const max = Math.max(...counts)
    hourlyChart = new Chart(hourlyChartRef.value, {
      type: 'bar',
      data: {
        labels: data.map(d => d.label),
        datasets: [
          {
            data: counts,
            backgroundColor: counts.map(v =>
              v >= max * 0.75 ? '#D85A30' : 'rgba(216,90,48,0.18)'
            ),
            borderRadius: 4,
            borderSkipped: false,
            borderWidth: 0
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => ctx.parsed.y + ' orders' } }
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { maxTicksLimit: 12, font: { size: 10 } }
          },
          y: {
            beginAtZero: true,
            grid: { display: false },
            ticks: { font: { size: 10 } }
          }
        }
      }
    })
  }
  const drawPayDonut = () => {
    if (!payDonutRef.value) return
    if (payDonut) payDonut.destroy()
    const data = paymentStats.value
    payDonut = new Chart(payDonutRef.value, {
      type: 'doughnut',
      data: {
        labels: data.map(d => d.label),
        datasets: [
          {
            data: data.map(d => d.revenue),
            backgroundColor: payColors.slice(0, data.length),
            borderWidth: 0
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: { label: ctx => ctx.label + ': ' + format(ctx.parsed) }
          }
        }
      }
    })
  }

  // ── Watchers ───────────────────────────────────────────────────────────────────
  watch(chartMode, () => drawLineChart())
  watch(hourlyMode, () => loadChartData())

  // Redraw charts when switching TO the charts tab (canvas may have been hidden)
  watch(activeTab, val => {
    if (val === 'charts')
      nextTick(() => {
        drawLineChart()
        drawHourlyChart()
      })
    if (val === 'overview') nextTick(() => drawPayDonut())
  })

  // ── Active filter badge (Orders tab filters only) ─────────────────────────────
  const activeFilterCount = computed(() => {
    let count = 0
    if (filters.value.search?.trim()) count++
    if (filters.value.order_type) count++
    if (filters.value.status) count++
    return count
  })
  const hasActiveFilters = computed(() => activeFilterCount.value > 0)

  // ── Actions ────────────────────────────────────────────────────────────────────
  let searchTimer = null
  const onSearch = () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => loadOrders(), 400)
  }

  // ── Reset to page 1 and reload when filters change ────────────────────────────
  const onFilterChange = () => {
    filters.value.page = 1
    loadOrders()
  }

  const resetFilters = () => {
    filters.value = {
      search: '',
      status: null,
      order_type: null,
      date_from: null,
      date_to: null,
      per_page: 15,
      page: 1
    }
    loadOrders()
  }
  const viewOrder = order => {
    selectedOrderId.value = order.id
    detailDialog.value = true
  }

  const exportExcel = async () => {
    exporting.value = true
    try {
      const dates = periodDates(period.value)
      const res = await orderStore.exportOrders({
        ...filters.value,
        date_from: filters.value.date_from ?? dates.from,
        date_to: filters.value.date_to ?? dates.to
      })
      const url = URL.createObjectURL(new Blob([res.data]))
      const link = document.createElement('a')
      link.href = url
      link.download = `Order-Report-${new Date().toISOString().slice(0, 10)}.xlsx`
      link.click()
      URL.revokeObjectURL(url)
      notif('Excel exported', { type: 'success' })
    } catch {
      notif('Export failed', { type: 'error' })
    } finally {
      exporting.value = false
    }
  }

  onMounted(() => loadAll())
</script>

<style scoped>
  /* ── Tab nav ───────────────────────────────────────────────────────────────── */
  .tab-nav-wrap {
    position: sticky;
    top: 0;
    z-index: 10;
    background: rgb(var(--v-theme-gray));
    padding: 4px 0 0;
  }
  .tab-nav {
    display: flex;
    gap: 4px;
    border-bottom: 2px solid rgba(0, 0, 0, 0.07);
    padding-bottom: 0;
  }
  .tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 500;
    color: rgba(0, 0, 0, 0.5);
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    cursor: pointer;
    border-radius: 6px 6px 0 0;
    transition:
      color 0.15s,
      border-color 0.15s,
      background 0.15s;
    white-space: nowrap;
  }
  .tab-btn:hover {
    color: rgba(0, 0, 0, 0.75);
    background: rgba(0, 0, 0, 0.03);
  }
  .tab-btn.active {
    color: rgb(var(--v-theme-primary));
    border-bottom-color: rgb(var(--v-theme-primary));
    font-weight: 600;
  }
  .tab-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    font-size: 10px;
    font-weight: 700;
    background: rgba(0, 0, 0, 0.08);
    border-radius: 9px;
    margin-left: 2px;
  }
  .tab-btn.active .tab-badge {
    background: rgba(var(--v-theme-primary), 0.15);
    color: rgb(var(--v-theme-primary));
  }

  /* ── KPI ───────────────────────────────────────────────────────────────────── */
  .kpi-card {
    transition:
      box-shadow 0.2s,
      transform 0.15s;
  }
  .kpi-card:hover {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
    transform: translateY(-1px);
  }
  .kpi-icon-wrap {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .kpi-value {
    font-size: 1.45rem;
    font-weight: 700;
    line-height: 1.2;
    letter-spacing: -0.5px;
  }
  .kpi-prev {
    opacity: 0.5;
    font-size: 10px;
  }

  /* ── Donut center ──────────────────────────────────────────────────────────── */
  .donut-center-label {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    pointer-events: none;
  }

  /* ── Legend dot ────────────────────────────────────────────────────────────── */
  .legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 2px;
    flex-shrink: 0;
  }

  /* ── Product bars ──────────────────────────────────────────────────────────── */
  .product-bar-track {
    height: 5px;
    background: rgba(0, 0, 0, 0.06);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 10px;
  }
  .product-bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
  }

  /* ── Podium ────────────────────────────────────────────────────────────────── */
  .top-product-podium {
    border: 1px solid transparent;
    transition: box-shadow 0.2s;
  }
  .podium-gold {
    background: linear-gradient(135deg, #cfc28e 0%, #fef9c3 100%);
    border-color: #fde68a !important;
  }
  .podium-silver {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-color: #e2e8f0 !important;
  }
  .podium-bronze {
    background: linear-gradient(135deg, #fff7f0 0%, #fef0e6 100%);
    border-color: #fed7aa !important;
  }
  .podium-medal {
    font-size: 18px;
    line-height: 1;
    flex-shrink: 0;
  }

  /* ── Insight pills ─────────────────────────────────────────────────────────── */
  .insight-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    background: rgba(var(--v-theme-on-surface), 0.04);
    font-size: 12px;
    color: rgba(var(--v-theme-on-surface), 0.65);
  }
  .insight-pill strong {
    font-weight: 600;
    color: rgba(var(--v-theme-on-surface), 1);
  }
  /* ── Filter bar + table ────────────────────────────────────────────────────── */
  .filter-bar {
    border-bottom: 1px solid rgba(0, 0, 0, 0.07);
  }
  .order-table :deep(tbody tr:nth-child(even)) {
    background: rgba(0, 0, 0, 0.015);
  }
  .top-product-podium {
    background: rgba(var(--v-theme-on-surface), 0.04) !important;
    border: 1px solid rgba(var(--v-theme-on-surface), 0.08) !important;
  }

  .podium-gold {
    border-color: rgba(255, 193, 7, 0.35) !important;
  }
  .podium-silver {
    border-color: rgba(var(--v-theme-on-surface), 0.12) !important;
  }
  .podium-bronze {
    border-color: rgba(180, 100, 30, 0.25) !important;
  }

  .podium-header {
    gap: 8px;
    margin-bottom: 8px;
  }

  .podium-medal {
    font-size: 20px;
  }

  .podium-name {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
</style>
