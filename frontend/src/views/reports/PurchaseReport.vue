<template>
  <v-container fluid class="pa-0">
    <custom-title icon="mdi-file-chart">
      Purchase Reports
      <template #right>
        <v-btn
          class="ms-2"
          variant="outlined"
          color="success"
          prepend-icon="mdi-file-excel"
          @click="generateReport"
        >
          Export Excel
        </v-btn>
      </template>
    </custom-title>

    <!-- Filters -->
    <v-card class="pa-4 mb-6" elevation="0">
      <v-row dense>
        <v-col cols="12" md="3">
          <v-date-input
            v-model="filters.from"
            label="From Date"
            hide-details
          ></v-date-input>
        </v-col>
        <v-col cols="12" md="3">
          <v-date-input
            v-model="filters.to"
            label="To Date"
            hide-details
            :allowed-dates="date => !filters.from || date >= filters.from"
          ></v-date-input>
        </v-col>
        <v-col cols="12" md="4">
          <v-select
            v-model="filters.category"
            :items="categoryStore.categories.data"
            item-title="name"
            item-value="id"
            label="Categories"
            multiple
            hide-details
          >
            <template v-slot:selection="{ item, index }">
              <v-chip v-if="index < 2" :text="item.title" size="x-small" />

              <span
                v-if="index === 2"
                class="text-grey text-caption align-self-center"
              >
                (+{{ filters.category.length - 2 }} others)
              </span>
            </template>
          </v-select>
        </v-col>
        <v-col cols="12" md="2" class="d-flex align-center">
          <v-btn
            color="primary"
            prepend-icon="mdi-filter"
            @click="applyFilters"
          >
            Apply filters
          </v-btn>
        </v-col>
      </v-row>
    </v-card>

    <!-- Summary Cards -->
    <v-row dense>
      <v-col v-for="kpi in kpis" :key="kpi.title" cols="12" sm="3" md="3">
        <v-card class="card pa-4" elevation="0" rounded="xl">
          <div class="d-flex justify-space-between align-center">
            <span class="text-kpi">{{ kpi.title }}</span>
            <v-icon :color="kpi.color" size="20">
              {{ kpi.icon }}
            </v-icon>
          </div>

          <div class="d-flex justify-space-between align-center mt-3">
            <h2 class="font-weight-bold">
              {{ kpi.value }}
            </h2>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Charts & Table -->
    <v-row dense class="mt-4 mb-4">
      <v-col cols="12" md="6">
        <v-card class="pa-6 rounded-lg" elevation="0">
          <h3 class="mb-4">Purchase Amount Trend</h3>
          <div style="height: 275px">
            <canvas ref="purchaseChart"></canvas>
          </div>
        </v-card>
      </v-col>

      <v-col cols="12" md="6">
        <v-card class="pa-6 rounded-lg" elevation="0">
          <h3 class="mb-4">Purchases by Category</h3>
          <div style="height: 275px">
            <canvas ref="categoryChart"></canvas>
          </div>
        </v-card>
      </v-col>
    </v-row>
    <!-- {{ reportStore.tableReport.data }} -->
    <v-data-table-server
      :headers="tableHeaders"
      :items="reportStore.tableReport.data"
      :items-length="reportStore.tableReport.total || 0"
      class="elevation-0"
    >
      <!-- v-model:items-per-page="reportItems.per_page" -->
      <template #item.total="{ item }">
        {{ formatCurrency(item.total) }}
      </template>
      <template #item.purchase_date="{ item }">
        {{ formatDate(item.purchase_date) }}
      </template>
      <template v-slot:top>
        <v-toolbar text class="bg-white">
          <v-toolbar-title>
            <v-icon
              color="medium-emphasis"
              icon="mdi-book-multiple"
              size="x-small"
              start
            ></v-icon>

            Purchase Report List
          </v-toolbar-title>
        </v-toolbar>
        <v-divider></v-divider>
      </template>
    </v-data-table-server>
  </v-container>
</template>

<script setup>
  import { ref, onMounted, watch } from 'vue'
  import Chart from 'chart.js/auto'
  import { useCategoryStore } from '@/stores/categoryStore'
  import { useReportStore } from '@/stores/reportStore'
  import { useCurrency } from '@/composables/useCurrency'
  import { useDate } from '@/composables/useDate'

  const categoryStore = useCategoryStore()
  const reportStore = useReportStore()
  const { formatCurrency } = useCurrency()
  const { formatDate } = useDate()

  const filters = ref({
    from: null, // 1st day of current month
    to: null, // last day of current month
    category: null
  })

  const kpis = ref([])

  // Watch the actual ref value
  watch(
    () => reportStore.kpisReport,
    newKpis => {
      if (!Array.isArray(newKpis)) return

      kpis.value = newKpis.map(item => {
        let value = item.value
        if (item.key == 'total_purchases' || item.key == 'avg_purchase_cost') {
          value = formatCurrency(Number(item.value))
        }

        return {
          title: item.title,
          value,
          icon: getKpiIcon(item.title),
          color: getKpiColor(item.title)
        }
      })
    },
    { immediate: true }
  )

  // Optional helper functions
  function getKpiIcon(title) {
    switch (title) {
      case 'Total Purchases':
        return 'mdi-cash-multiple'
      case 'Purchase Orders':
        return 'mdi-file-document-multiple'
      case 'Total Quantity':
        return 'mdi-package-variant'
      case 'Avg Purchase Cost':
        return 'mdi-chart-line'
      default:
        return 'mdi-chart-bar'
    }
  }

  function getKpiColor(title) {
    switch (title) {
      case 'Total Purchases':
        return 'success'
      case 'Purchase Orders':
        return 'primary'
      case 'Total Quantity':
        return 'info'
      case 'Avg Purchase Cost':
        return 'purple'
      default:
        return 'grey'
    }
  }

  const tableHeaders = [
    { title: 'Date', value: 'purchase.purchase_date', key: 'purchase_date' },
    { title: 'Purchase No', value: 'purchase.invoice_number' },
    { title: 'Supplier', value: 'purchase.supplier.name' },
    { title: 'Category', value: 'product.category.name' },
    { title: 'Quantity', value: 'quantity' },
    { title: 'Total Cost ($)', value: 'total', key: 'total' }
  ]

  watch(
    () => reportStore?.purchaseReport,
    () => renderCharts()
  )

  const purchaseChart = ref(null)
  const categoryChart = ref(null)

  let purchaseChartInstance = null
  let categoryChartInstance = null

  function renderCharts() {
    if (!reportStore.purchaseReport) return

    const trendData = reportStore.purchaseReport.trend || {}
    const byCategoryData = reportStore.purchaseReport.byCategory || {}

    // Destroy previous charts
    if (purchaseChartInstance) purchaseChartInstance.destroy()
    if (categoryChartInstance) categoryChartInstance.destroy()

    // Purchase Amount Trend (line chart)
    purchaseChartInstance = new Chart(purchaseChart.value.getContext('2d'), {
      type: 'line',
      data: {
        labels: Object.keys(trendData),
        datasets: [
          {
            label: 'Purchase Amount ($)',
            data: Object.values(trendData),
            borderColor: '#42A5F5',
            backgroundColor: '#42A5F5',
            borderWidth: 2,
            tension: 0.4,
            fill: false
          }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false }
    })

    // Purchases by Category (bar chart)
    categoryChartInstance = new Chart(categoryChart.value.getContext('2d'), {
      type: 'bar',
      data: {
        labels: Object.keys(byCategoryData),
        datasets: [
          {
            label: 'Purchase Cost ($)',
            data: Object.values(byCategoryData),
            backgroundColor: '#66BB6A'
          }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false }
    })
  }

  function generateReport() {
    // Call API with filters.value to fetch new data
    renderCharts()
  }

  async function applyFilters() {
    const payload = {
      from: filters.value.from,
      to: filters.value.to,
      category: filters.value.category?.join(',') ?? ''
    }
    await reportStore.fetchReportsPurchases(payload)
    renderCharts() // Make sure charts update after filtered data
  }

  onMounted(() => {
    renderCharts()
    categoryStore.fetchCategories({
      per_page: -1
    })
    const payload = {
      from:
        filters.value.from ??
        new Date(new Date().getFullYear(), new Date().getMonth(), 1), // default first day of month
      to:
        filters.value.to ??
        new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0), // default last day of month
      category: filters.value.category?.join(',') ?? ''
    }
    reportStore.fetchReportsPurchases(payload)
  })
</script>

<style scoped></style>
