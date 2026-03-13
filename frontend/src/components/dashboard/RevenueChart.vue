<template>
  <v-card rounded="xl" elevation="0" border class="mb-4">
    <v-card-title class="pa-5 pb-3">
      <div class="d-flex align-center justify-space-between">
        <div>
          <div class="text-subtitle-1 font-weight-bold">Revenue Overview</div>
          <div class="text-caption text-medium-emphasis">All branches combined</div>
        </div>
        <v-btn-toggle
          v-model="chartMode"
          mandatory density="compact"
          rounded="lg" variant="outlined" color="primary"
        >
          <v-btn value="revenue" size="small">Revenue</v-btn>
          <v-btn value="orders"  size="small">Orders</v-btn>
        </v-btn-toggle>
      </div>
    </v-card-title>
    <v-card-text class="pa-5 pt-2">
      <v-skeleton-loader v-if="loading" type="image" height="200" />
      <div v-else style="position: relative; height: 220px">
        <canvas ref="canvasRef" />
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { Chart, registerables } from 'chart.js'

Chart.register(...registerables)

const props = defineProps({
  chartData: { type: Array, default: () => [] }, // [{ label, revenue, orders }]
  loading:   { type: Boolean, default: false },
})

const canvasRef = ref(null)
const chartMode = ref('revenue')
let chart = null

const primaryColor = '#1867C0'
const fontFamily   = "'Inter', sans-serif"

const buildChart = () => {
  if (!canvasRef.value || !props.chartData.length) return
  if (chart) chart.destroy()

  const labels = props.chartData.map(r => r.label)
  const values = props.chartData.map(r => chartMode.value === 'revenue' ? r.revenue : r.orders)

  chart = new Chart(canvasRef.value, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        data: values,
        borderColor: primaryColor,
        borderWidth: 2.5,
        pointBackgroundColor: '#fff',
        pointBorderColor: primaryColor,
        pointBorderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 6,
        fill: true,
        backgroundColor: (ctx) => {
          const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 220)
          gradient.addColorStop(0, 'rgba(24,103,192,0.18)')
          gradient.addColorStop(1, 'rgba(24,103,192,0)')
          return gradient
        },
        tension: 0.4,
      }],
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
            label: ctx => chartMode.value === 'revenue'
              ? ` $${ctx.parsed.y.toLocaleString()}`
              : ` ${ctx.parsed.y} orders`,
          },
        },
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { font: { family: fontFamily, size: 11 }, color: '#9e9e9e' },
        },
        y: {
          grid: { color: 'rgba(0,0,0,0.05)' },
          beginAtZero: true,
          ticks: {
            font: { family: fontFamily, size: 11 },
            color: '#9e9e9e',
            callback: v => chartMode.value === 'revenue' ? `$${v.toLocaleString()}` : v,
          },
        },
      },
    },
  })
}

// Toggle revenue/orders — update data in place
watch(chartMode, () => {
  if (!chart) return
  const values = props.chartData.map(r => chartMode.value === 'revenue' ? r.revenue : r.orders)
  chart.data.datasets[0].data = values
  chart.options.scales.y.ticks.callback = v =>
    chartMode.value === 'revenue' ? `$${v.toLocaleString()}` : v
  chart.update('active')
})

// Rebuild when data arrives
watch(() => props.loading, async (loading) => {
  if (!loading) { await nextTick(); buildChart() }
})

watch(() => props.chartData, async () => {
  await nextTick(); buildChart()
}, { deep: true })

onMounted(async () => { await nextTick(); buildChart() })
onBeforeUnmount(() => chart?.destroy())
</script>