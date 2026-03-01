<template>
  <!-- PAGE TITLE -->
  <custom-title icon="mdi-warehouse">
    Inventory Report
    <template #right>
      <v-btn
        class="ms-2"
        variant="outlined"
        color="success"
        prepend-icon="mdi-file-excel"
      >
        Export Excel
      </v-btn>
    </template>
  </custom-title>

  <!-- FILTERS -->
  <v-card elevation="0" class="mb-6 pa-4 rounded-lg">
    <v-row dense>
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

      <v-col cols="12" md="3">
        <v-select
          label="Stock Status"
          :items="statusOptions"
          v-model="filters.status"
          hide-details
        />
      </v-col>

      <v-col cols="12" md="3" class="d-flex align-center">
        <v-btn color="primary" prepend-icon="mdi-filter" @click="applyFilter">
          Apply Filter
        </v-btn>
      </v-col>
    </v-row>
  </v-card>

  <!-- KPI SUMMARY -->
  <v-row dense>
    <v-col v-for="kpi in kpis" :key="kpi.title" cols="12" sm="6" md="3">
      <v-card class="pa-4" elevation="0" rounded="xl">
        <div class="d-flex justify-space-between align-center">
          <span class="text-subtitle-2">{{ kpi.title }}</span>
          <v-icon :color="kpi.color">{{ kpi.icon }}</v-icon>
        </div>
        <h2 class="font-weight-bold mt-3">
          {{ kpi.value }}
        </h2>
      </v-card>
    </v-col>
  </v-row>

  <!-- CHART -->
  <v-row dense class="mt-6">
    <v-col cols="12">
      <v-card elevation="0" class="pa-4 rounded-lg">
        <div class="text-subtitle-1 font-weight-medium mb-3">
          Stock by Category
        </div>
        <div class="chart-wrapper">
          <canvas ref="categoryChart"></canvas>
        </div>
      </v-card>
    </v-col>
  </v-row>

  <!-- INVENTORY TABLE -->
  <v-card elevation="0" class="rounded-lg mt-6">
    <v-data-table :headers="headers" :items="items" density="comfortable">
      <template #item.status="{ value }">
        <v-chip
          size="small"
          :color="
            value === 'Low' ? 'warning' : value === 'Out' ? 'error' : 'success'
          "
        >
          {{ value }}
        </v-chip>
      </template>
    </v-data-table>
  </v-card>
</template>

<script setup>
  import { ref, computed, onMounted, watch } from 'vue'
  import { Chart } from 'chart.js/auto'
  import { useCategoryStore } from '@/stores/categoryStore'
  import { useReportStore } from '@/stores/reportStore'

  const categoryStore = useCategoryStore()
  const reportStore = useReportStore()

  const filters = ref({
    category: null,
    status: null
  })

  const statusOptions = ['In Stock', 'Low', 'Out']

  /* =====================
   COMPUTED DATA
===================== */
  const kpis = computed(() => reportStore.inventoryReport?.kpis ?? [])
  const items = computed(() => reportStore.inventoryReport?.table ?? [])

  const headers = [
    { title: 'Product', value: 'name' },
    { title: 'SKU', value: 'sku' },
    { title: 'Category', value: 'category' },
    { title: 'Quantity', value: 'qty' },
    { title: 'Unit Price', value: 'price' },
    { title: 'Status', value: 'status' }
  ]

  /* =====================
   CHART
===================== */
  const categoryChart = ref(null)
  let chartInstance = null

  const renderChart = () => {
    if (!reportStore.inventoryReport?.chart) return

    if (chartInstance) chartInstance.destroy()

    chartInstance = new Chart(categoryChart.value, {
      type: 'bar',
      data: {
        labels: reportStore.inventoryReport.chart.labels,
        datasets: [
          {
            label: 'Stock Quantity',
            data: reportStore.inventoryReport.chart.data,
            borderWidth: 1
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false
      }
    })
  }

  watch(
    () => reportStore.inventoryReport,
    () => renderChart()
  )

  /* =====================
   ACTIONS
===================== */
  const applyFilter = () => {
    reportStore.fetchReportsInventory({
      category: filters.value.category?.join(',') ?? '',
      status: filters.value.status
    })
  }

  onMounted(() => {
    categoryStore.fetchCategories({ per_page: -1 })
    reportStore.fetchReportsInventory()
  })
</script>

<style scoped>
  .chart-wrapper {
    height: 300px;
  }
</style>
