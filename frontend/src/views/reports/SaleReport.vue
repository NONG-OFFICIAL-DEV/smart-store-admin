<template>
  <v-container fluid class="pa-0">
    <custom-title icon="mdi-sale">
      Sales Report
      <template #right>
        <BaseButtonFilter class="me-4" @click="toggleFilterForm" />
        <v-btn color="primary" prepend-icon="mdi-download" @click="exportData">
          Export CSV
        </v-btn>
      </template>
    </custom-title>
    <v-card flat class="pa-4 mb-4 rounded-xl border" v-show="showFilterForm">
      <v-row align="center">
        <v-col cols="12" md="3">
          <v-select
            v-model="selectedRange"
            :items="rangeOptions"
            label="Timeframe"
            variant="outlined"
            density="compact"
            hide-details
            @update:model-value="handleRangeChange"
          ></v-select>
        </v-col>

        <v-fade-transition>
          <v-col cols="12" md="5" v-if="selectedRange === 'custom'">
            <div class="d-flex ga-2">
              <v-date-input
                v-model="filters.from"
                label="From"
                density="compact"
                hide-details
                variant="outlined"
              />
              <v-date-input
                v-model="filters.to"
                label="To"
                density="compact"
                hide-details
                variant="outlined"
              />
            </div>
          </v-col>
        </v-fade-transition>

        <v-spacer></v-spacer>
        <v-col
          cols="12"
          md="4"
          v-if="showGranularity"
          class="d-flex justify-md-end align-center"
        >
          <v-fade-transition>
            <div class="d-flex align-center">
              <span class="text-caption mr-2 text-grey text-no-wrap">
                Group by:
              </span>
              <v-btn-toggle
                v-model="granularity"
                color="#3b828e"
                density="compact"
                mandatory
                variant="outlined"
              >
                <v-btn value="day">Day</v-btn>
                <v-btn value="week" v-if="isRangeLongerThanWeek">Week</v-btn>
                <v-btn value="month" v-if="isRangeLongerThanMonth">Month</v-btn>
              </v-btn-toggle>
            </div>
          </v-fade-transition>
        </v-col>

        <v-col cols="12" class="d-flex justify-end pt-4">
          <v-btn
            color="primary"
            size="large"
            rounded="pill"
            elevation="0"
            class="text-none"
          >
            <!-- :loading="loadingStore.isLoading"
            @click="applyFilters" -->
            <v-icon start>mdi-filter-check</v-icon>
            Apply Filters
          </v-btn>
        </v-col>
      </v-row>
    </v-card>
    <v-row>
      <v-col v-for="kpi in kpiData" :key="kpi.title" cols="12" sm="6" md="3">
        <v-card border flat class="pa-4">
          <div class="d-flex justify-space-between align-start mb-2">
            <div>
              <span class="text-overline text-grey-darken-1">
                {{ kpi.title }}
              </span>
              <h2 class="text-h4 font-weight-bold">{{ kpi.value }}</h2>
            </div>
            <v-avatar :color="kpi.color" variant="tonal" rounded="lg">
              <v-icon :icon="kpi.icon"></v-icon>
            </v-avatar>
          </div>

          <div class="d-flex align-center mt-4">
            <v-icon
              :color="kpi.trend > 0 ? 'success' : 'error'"
              size="small"
              class="mr-1"
            >
              {{ kpi.trend > 0 ? 'mdi-arrow-up' : 'mdi-arrow-down' }}
            </v-icon>
            <span
              :class="kpi.trend > 0 ? 'text-success' : 'text-error'"
              class="text-caption font-weight-bold"
            >
              {{ Math.abs(kpi.trend) }}%
            </span>
            <span class="text-caption text-grey ml-1">vs last period</span>
          </div>

          <v-sparkline
            :model-value="kpi.history"
            :color="kpi.color"
            height="40"
            padding="8"
            stroke-linecap="round"
            smooth
            auto-draw
          ></v-sparkline>
        </v-card>
      </v-col>
    </v-row>
    <v-row>
      <v-col cols="12" md="8">
        <v-card border flat class="pa-4">
          <div class="d-flex justify-space-between align-center mb-4">
            <div>
              <v-card-title class="pa-0">Revenue Trend</v-card-title>
              <v-card-subtitle class="pa-0">
                Performance over the selected period
              </v-card-subtitle>
            </div>
            <v-chip size="small" color="primary" variant="outlined">
              Live Data
            </v-chip>
          </div>

          <div style="height: 350px">
            <Line :data="lineChartData" :options="lineChartOptions" />
          </div>
        </v-card>
      </v-col>

      <v-col cols="12" md="4">
        <v-card border flat class="pa-4">
          <v-card-title class="pa-0">Sales by Category</v-card-title>
          <v-card-subtitle class="pa-0 mb-4">
            Top performing areas
          </v-card-subtitle>

          <div style="height: 350px">
            <Bar :data="barChartData" :options="barChartOptions" />
          </div>
        </v-card>
      </v-col>
    </v-row>
    <v-row>
      <v-col cols="12" md="6">
        <v-card border flat>
          <v-card-title class="d-flex align-center pe-2">
            Recent Transactions
            <v-spacer></v-spacer>
            <v-text-field
              v-model="search"
              density="compact"
              label="Search orders..."
              prepend-inner-icon="mdi-magnify"
              flat
              hide-details
              single-line
            ></v-text-field>
          </v-card-title>
          <v-data-table
            :headers="headers"
            :items="tableData.data"
            :search="search"
            hover
            density="compact"
          >
            <template v-slot:item.sale_date="{ item }">
              {{ formatDate(item.sale_date) }}
            </template>
            <template v-slot:item.status="{ item }">
              <v-chip
                size="small"
                class="text-capitalize"
                :color="getStatusColor(item.status)"
              >
                {{ item.status }}
              </v-chip>
            </template>
            <template v-slot:item.total_amount="{ item }">
              {{ formatCurrency(item.total_amount) }}
            </template>
          </v-data-table>
        </v-card>
      </v-col>
      <v-col cols="12" md="6">
        <v-card border flat class="pa-4">
          <div class="d-flex justify-space-between align-center mb-4">
            <div>
              <v-card-title class="pa-0">Top Menu Popularity</v-card-title>
              <v-card-subtitle class="pa-0">
                Ranked by quantity sold
              </v-card-subtitle>
            </div>
            <v-icon color="secondary">mdi-fire</v-icon>
          </div>

          <v-table density="compact">
            <thead>
              <tr>
                <th class="text-left">Item</th>
                <th class="text-center">Orders</th>
                <th class="text-right">Trend</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in topOrderedData" :key="item.name">
                <td>{{ item.name }}</td>
                <td class="text-center font-weight-bold">{{ item.qty }}</td>
                <td class="text-right">
                  <v-chip
                    :color="item.growth > 0 ? 'success' : 'error'"
                    size="x-small"
                    variant="tonal"
                    label
                  >
                    {{ item.growth > 0 ? '+' : '' }}{{ item.growth }}%
                  </v-chip>
                </td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
  import { ref, onMounted, reactive, computed } from 'vue'
  import { useSaleStore } from '../../stores/saleStore'
  import { useCurrency } from '@/composables/useCurrency'
  import { useDate } from '@/composables/useDate'

  import { Line, Bar } from 'vue-chartjs'
  import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    LineElement,
    LinearScale,
    PointElement,
    CategoryScale,
    BarElement
  } from 'chart.js'

  ChartJS.register(
    Title,
    Tooltip,
    Legend,
    LineElement,
    LinearScale,
    PointElement,
    CategoryScale,
    BarElement
  )
  const { formatCurrency } = useCurrency()
  const { formatDate } = useDate()

  // Search logic
  const search = ref('')
  const saleStore = useSaleStore()
  const tableData = ref([])
  const summaryKpi = ref([])
  const filters = reactive({ search: '', status: null, from: null, to: null })
  const showFilterForm = ref(false)
  const selectedRange = ref('thisMonth')
  const granularity = ref('day')

  const rangeOptions = [
    { title: 'Today', value: 'today' },
    { title: 'Yesterday', value: 'yesterday' },
    { title: 'Last 7 Days', value: '7days' },
    { title: 'This Month', value: 'thisMonth' },
    { title: 'This Year', value: 'thisYear' },
    { title: 'Custom Range', value: 'custom' }
  ]

  // Logic: Hide granularity for "Today" or "Yesterday"
  const showGranularity = computed(() => {
    const singleDayRanges = ['today', 'yesterday']
    return !singleDayRanges.includes(selectedRange.value)
  })

  // Logic: Show "Week" only if range is > 7 days
  const isRangeLongerThanWeek = computed(() => {
    return ['thisMonth', 'thisYear', 'custom'].includes(selectedRange.value)
  })

  // Logic: Show "Month" only if range is very long
  const isRangeLongerThanMonth = computed(() => {
    return ['thisYear', 'custom'].includes(selectedRange.value)
  })

  // Function to reset granularity when range changes
  const handleRangeChange = val => {
    if (val === 'today' || val === 'yesterday') {
      granularity.value = 'day'
    }
  }
  const kpiData = computed(() =>[
    {
      title: 'Total Revenue',
      value: summaryKpi.value.total_revenue ?? 0,
      icon: 'mdi-currency-usd',
      color: 'primary',
      trend: 15.2,
      history: [400, 390, 450, 400, 480, 440, 500] // Fake mini-chart data
    },
    {
      title: 'Avg. Order Value',
      value: summaryKpi.value.avg_value ?? 0,
      icon: 'mdi-cart-percent',
      color: 'info',
      trend: -2.4,
      history: [120, 125, 122, 130, 128, 124, 124]
    },
    {
      title: 'Orders',
      value: '345',
      icon: 'mdi-package-variant-closed',
      color: 'warning',
      trend: 8.1,
      history: [20, 25, 30, 22, 28, 35, 34]
    },
    {
      title: 'Conversion Rate',
      value: '3.2%',
      icon: 'mdi-trending-up',
      color: 'success',
      trend: 0.5,
      history: [2.8, 2.9, 3.1, 3.0, 3.3, 3.2, 3.2]
    }
  ])
  const lineChartData = {
    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
    datasets: [
      {
        label: 'Current Period',
        backgroundColor: '#1867C0', // Vuetify Primary
        borderColor: '#1867C0',
        data: [30, 52, 45, 70, 62, 85, 90],
        tension: 0.4, // Makes the line smooth
        fill: true
      },
      {
        label: 'Previous Period',
        backgroundColor: '#E0E0E0',
        borderColor: '#BDBDBD',
        borderDash: [5, 5], // Dashed line
        data: [25, 40, 38, 55, 50, 60, 65],
        tension: 0.4
      }
    ]
  }

  const lineChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'bottom' }
    }
  }

  // Bar Chart: Categories
  const barChartData = {
    labels: ['Software', 'Hardware', 'Services'],
    datasets: [
      {
        label: 'Revenue',
        backgroundColor: ['#1867C0', '#5CBBFF', '#009688'],
        borderColor: '#1867C0',
        data: [12000, 8500, 4200]
      }
    ]
  }

  const barChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y', // Makes it a horizontal bar chart
    plugins: { legend: { display: false } }
  }
  onMounted(async () => {
    // Fetch sales report data
    const reportData = await saleStore.saleReport()
    saleStore.topMenusReport(filters.from, filters.to)
    tableData.value = reportData.table_data
    summaryKpi.value = reportData.summary
  })
  const toggleFilterForm = () => {
    showFilterForm.value = !showFilterForm.value
  }
  // Table Configuration
  const headers = [
    { title: 'Invoice', key: 'invoice_no' },
    { title: 'Date', key: 'sale_date' },
    { title: 'Items', key: 'items_count' },
    { title: 'Total', key: 'total_amount' },
    { title: 'Status', key: 'status' }
  ]

  // Helper functions
  const getStatusColor = status => {
    if (status === 'paid') return 'success'
    if (status === 'Pending') return 'warning'
    return 'grey'
  }


  // Fake Data for Top Ordered (Quantity)
  const topOrderedData = ref([
    { name: 'Basic License', qty: 1450, growth: 12 },
    { name: 'API Access Token', qty: 980, growth: 24 },
    { name: 'Security Patch', qty: 820, growth: -5 },
    { name: 'User Seat Add-on', qty: 650, growth: 8 },
    { name: 'Storage Bump (1TB)', qty: 420, growth: 15 }
  ])

  const exportData = () => {
    alert('Exporting data to CSV...')
  }
</script>
