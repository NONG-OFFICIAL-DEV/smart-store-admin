<template>
  <v-card rounded="xl" border elevation="0" class="pa-5 fill-height">
    <div class="text-body-2 font-weight-bold mb-4">
      {{ t('inventory_report.overview.stock_health', 'Stock Health') }}
    </div>

    <div class="health-layout">
      <StockDonutChart
        :in-stock="inStock"
        :low-stock="lowStock"
        :out-of-stock="outOfStock"
        :label="t('inventory_report.status.in_stock')"
      />

      <div class="legend-list">
        <div v-for="leg in legend" :key="leg.key" class="legend-row">
          <div class="legend-left">
            <span class="legend-dot" :style="{ background: `rgb(var(--v-theme-${leg.color}))` }" />
            <span class="text-body-2">{{ leg.label }}</span>
          </div>
          <div class="legend-right">
            <span class="text-body-2 font-weight-black" :class="`text-${leg.color}`">
              {{ leg.count }}
            </span>
            <v-chip
              size="x-small"
              :color="leg.color"
              variant="tonal"
              rounded="lg"
              class="pct-chip"
            >
              {{ total > 0 ? Math.round((leg.count / total) * 100) : 0 }}%
            </v-chip>
          </div>
        </div>
      </div>
    </div>
  </v-card>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import StockDonutChart from './StockDonutChart.vue'

const { t } = useI18n()

const props = defineProps({
  inStock:    { type: Number, default: 0 },
  lowStock:   { type: Number, default: 0 },
  outOfStock: { type: Number, default: 0 }
})

const total = computed(() => props.inStock + props.lowStock + props.outOfStock)

const legend = computed(() => [
  { key: 'in_stock',     color: 'success', label: t('inventory_report.status.in_stock'),     count: props.inStock },
  { key: 'low_stock',    color: 'warning', label: t('inventory_report.status.low_stock'),    count: props.lowStock },
  { key: 'out_of_stock', color: 'error',   label: t('inventory_report.status.out_of_stock'), count: props.outOfStock }
])
</script>

<style scoped>
.health-layout {
  display: flex;
  align-items: center;
  gap: 24px;
}
.legend-list {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.legend-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.legend-left {
  display: flex;
  align-items: center;
  gap: 8px;
}
.legend-right {
  display: flex;
  align-items: center;
  gap: 8px;
}
.legend-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.pct-chip {
  min-width: 42px;
  justify-content: center;
}
</style>