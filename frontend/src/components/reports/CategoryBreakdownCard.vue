<template>
  <v-card rounded="xl" border elevation="0" class="fill-height category-card">
    <div class="card-header">
      <span class="text-body-2 font-weight-bold">
        {{ t('inventory_report.by_category', 'By Category') }}
      </span>
      <div class="bar-legend">
        <div v-for="leg in barLegend" :key="leg.key" class="bar-legend-item">
          <span
            class="legend-dot"
            :style="{ background: `rgb(var(--v-theme-${leg.color}))` }"
          />
          <span class="text-caption text-medium-emphasis">{{ leg.label }}</span>
        </div>
      </div>
    </div>

    <v-divider />

    <div class="category-scroll">
      <template v-if="categories.length">
        <div v-for="cat in categories" :key="cat.category" class="cat-row">
          <!-- Row header -->
          <div class="cat-header">
            <div class="cat-name-wrap">
              <span class="text-body-2 font-weight-medium cat-name">
                {{ cat.category }}
              </span>
              <v-chip
                v-if="cat.out_of_stock"
                size="x-small"
                color="error"
                variant="tonal"
                rounded="lg"
              >
                {{ cat.out_of_stock }} out
              </v-chip>
              <v-chip
                v-if="cat.low_stock"
                size="x-small"
                color="warning"
                variant="tonal"
                rounded="lg"
              >
                {{ cat.low_stock }} low
              </v-chip>
            </div>
            <div class="cat-meta">
              <span class="text-caption text-medium-emphasis">
                {{ cat.count }} items
              </span>
              <span class="text-caption font-weight-bold cat-value">
                {{ format(cat.stock_value) }}
              </span>
            </div>
          </div>
          <!-- Stacked bar -->
          <div class="stacked-bar">
            <div
              class="bar-in"
              :style="{ width: catPct(cat, 'in_stock') + '%' }"
            />
            <div
              class="bar-low"
              :style="{ width: catPct(cat, 'low_stock') + '%' }"
            />
            <div
              class="bar-out"
              :style="{ width: catPct(cat, 'out_of_stock') + '%' }"
            />
          </div>
        </div>
      </template>
      <div v-else class="text-caption text-medium-emphasis text-center py-8">
        No category data
      </div>
    </div>
  </v-card>
</template>

<script setup>
  import { computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useCurrency } from '@/composables/useCurrency_v2.js'

  const { t } = useI18n()
  const { format } = useCurrency()

  defineProps({
    categories: { type: Array, default: () => [] }
  })

  const barLegend = computed(() => [
    {
      key: 'in',
      color: 'success',
      label: t('inventory_report.status.in_stock')
    },
    {
      key: 'low',
      color: 'warning',
      label: t('inventory_report.status.low_stock')
    },
    {
      key: 'out',
      color: 'error',
      label: t('inventory_report.status.out_of_stock')
    }
  ])

  const catPct = (cat, key) => {
    const total = cat.count || 1
    const inStock = Math.max(
      total - (cat.low_stock ?? 0) - (cat.out_of_stock ?? 0),
      0
    )
    const values = {
      in_stock: inStock,
      low_stock: cat.low_stock ?? 0,
      out_of_stock: cat.out_of_stock ?? 0
    }
    return Math.round((values[key] / total) * 100)
  }
</script>

<style scoped>
  .category-card {
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }
  .card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 20px 12px;
    flex-shrink: 0;
  }
  .bar-legend {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .bar-legend-item {
    display: flex;
    align-items: center;
    gap: 4px;
  }
  .legend-dot {
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
  }
  .category-scroll {
    overflow-y: auto;
    flex: 1;
    min-height: 0; /* add this — critical for flex scroll to work */
    max-height: 260px; /* add this — caps height so scroll triggers */
    padding: 12px 20px;
    scrollbar-width: thin;
    scrollbar-color: rgba(0, 0, 0, 0.12) transparent;
  }
  .category-scroll::-webkit-scrollbar {
    width: 4px;
  }
  .category-scroll::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.12);
    border-radius: 4px;
  }
  .cat-row {
    margin-bottom: 14px;
  }
  .cat-row:last-child {
    margin-bottom: 0;
  }
  .cat-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 4px;
  }
  .cat-name-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
    flex: 1;
  }
  .cat-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .cat-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
    margin-left: 8px;
  }
  .cat-value {
    min-width: 72px;
    text-align: right;
  }
  .stacked-bar {
    display: flex;
    height: 6px;
    border-radius: 3px;
    overflow: hidden;
    background: #f1f5f9;
  }
  .bar-in {
    background: rgb(var(--v-theme-success));
    transition: width 0.45s ease;
  }
  .bar-low {
    background: rgb(var(--v-theme-warning));
    transition: width 0.45s ease;
  }
  .bar-out {
    background: rgb(var(--v-theme-error));
    transition: width 0.45s ease;
  }
</style>
