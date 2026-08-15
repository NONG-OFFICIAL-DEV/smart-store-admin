<template>
  <div class="donut-wrap">
    <canvas ref="canvasRef" width="120" height="120" />
    <div class="donut-center">
      <span class="donut-pct">{{ pct }}%</span>
      <span class="donut-label">{{ label }}</span>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount, computed } from 'vue'
import { Chart, DoughnutController, ArcElement, Tooltip } from 'chart.js'
import { useI18n } from 'vue-i18n'

Chart.register(DoughnutController, ArcElement, Tooltip)

const { t } = useI18n()

const props = defineProps({
  inStock:    { type: Number, default: 0 },
  lowStock:   { type: Number, default: 0 },
  outOfStock: { type: Number, default: 0 },
  label:      { type: String, default: undefined }
})

const canvasRef = ref(null)
let chart = null

const total = computed(() => props.inStock + props.lowStock + props.outOfStock)
const pct   = computed(() =>
  total.value > 0 ? Math.round((props.inStock / total.value) * 100) : 0
)
// Prop defaults can't call `t()` directly — defineProps() default factories
// are hoisted out of setup(), so they can't close over the useI18n() `t`.
const label = computed(() => props.label ?? t('inventory_report.status.in_stock'))

// Read CSS vars at runtime so Chart.js colours match the Vuetify theme
const themeColor = name => {
  const raw = getComputedStyle(document.documentElement)
    .getPropertyValue(`--v-theme-${name}`)
    .trim()
  // Vuetify stores colours as "R G B" triplets
  return raw ? `rgb(${raw})` : '#ccc'
}

const buildChart = () => {
  if (!canvasRef.value) return
  const isEmpty = total.value === 0

  chart = new Chart(canvasRef.value, {
    type: 'doughnut',
    data: {
      datasets: [{
        data: isEmpty
          ? [1]
          : [props.inStock, props.lowStock, props.outOfStock],
        backgroundColor: isEmpty
          ? ['#e2e8f0']
          : [
              themeColor('success'),
              themeColor('warning'),
              themeColor('error')
            ],
        borderWidth: 0,
        hoverOffset: isEmpty ? 0 : 4
      }]
    },
    options: {
      cutout: '72%',
      plugins: { tooltip: { enabled: !isEmpty }, legend: { display: false } },
      animation: { duration: 600, easing: 'easeInOutQuart' }
    }
  })
}

const updateChart = () => {
  if (!chart) return
  const isEmpty = total.value === 0
  chart.data.datasets[0].data = isEmpty
    ? [1]
    : [props.inStock, props.lowStock, props.outOfStock]
  chart.data.datasets[0].backgroundColor = isEmpty
    ? ['#e2e8f0']
    : [themeColor('success'), themeColor('warning'), themeColor('error')]
  chart.update()
}

onMounted(buildChart)
onBeforeUnmount(() => chart?.destroy())

watch(() => [props.inStock, props.lowStock, props.outOfStock], updateChart)
</script>

<style scoped>
.donut-wrap {
  position: relative;
  width: 120px;
  height: 120px;
  flex-shrink: 0;
}

.donut-center {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  pointer-events: none;
}

.donut-pct {
  font-size: 1.25rem;
  font-weight: 900;
  line-height: 1;
}

.donut-label {
  font-size: 9px;
  letter-spacing: 0.06em;
  color: rgba(0, 0, 0, 0.45);
  margin-top: 2px;
}
</style>