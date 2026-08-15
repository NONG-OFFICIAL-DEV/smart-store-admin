<template>
  <div>
    <!-- Filters -->
    <div class="filter-bar">
      <v-row dense align="center">
        <v-col cols="12" sm="5">
          <v-text-field
            :model-value="search"
            :placeholder="t('inventory_report.filter.search_placeholder')"
            variant="outlined"
            density="compact"
            rounded="lg"
            hide-details
            clearable
            prepend-inner-icon="mdi-magnify"
            @update:model-value="emit('update:search', $event)"
          />
        </v-col>
        <v-col cols="12" sm="7" class="d-flex align-center">
          <v-chip-group
            :model-value="stockFilter"
            selected-class="font-weight-bold"
            @update:model-value="emit('update:stockFilter', $event)"
          >
            <v-chip
              v-for="opt in filterOptions"
              :key="opt.value"
              :value="opt.value"
              :color="opt.color"
              size="small"
              variant="tonal"
              filter
              rounded="lg"
            >
              {{ opt.label }}
            </v-chip>
          </v-chip-group>
        </v-col>
      </v-row>
    </div>

    <v-divider />

    <v-data-table
      :headers="headers"
      :items="products"
      :items-per-page="10"
      item-value="id"
      :loading="loading"
    >
      <template #item.name="{ item }">
        <div class="product-cell">
          <v-avatar
            size="36"
            rounded="lg"
            class="bg-grey-lighten-4 border flex-shrink-0"
          >
            <v-img v-if="item.image_url" :src="item.image_url" cover />
            <v-icon v-else icon="mdi-package-variant" size="16" color="grey" />
          </v-avatar>
          <div>
            <div class="text-body-2 font-weight-bold">{{ item.name }}</div>
            <div class="text-caption text-medium-emphasis">
              {{ item.sku ?? '—' }}
            </div>
          </div>
        </div>
      </template>

      <template #item.category="{ item }">
        <span class="text-caption">{{ item.category ?? '—' }}</span>
      </template>

      <template #item.stock_quantity="{ item }">
        <div class="d-flex align-center gap-1">
          <span
            class="font-weight-black"
            :class="stockClass(item.stock_status)"
          >
            {{ item.stock_quantity }}
          </span>
          <span class="text-caption text-medium-emphasis">{{ item.unit }}</span>
        </div>
        <v-progress-linear
          v-if="item.reorder_level"
          :model-value="
            Math.min(
              (item.stock_quantity / (item.reorder_level * 2)) * 100,
              100
            )
          "
          :color="statusColor(item.stock_status)"
          height="3"
          rounded
          class="mt-1"
        />
      </template>

      <template #item.reorder_level="{ item }">
        <span class="text-body-2">{{ item.reorder_level ?? '—' }}</span>
      </template>

      <template #item.cost_price="{ item }">
        <span class="text-body-2">{{ format(item.cost_price) }}</span>
      </template>

      <template #item.retail_price="{ item }">
        <span class="text-body-2">{{ format(item.retail_price) }}</span>
      </template>

      <template #item.stock_value="{ item }">
        <span class="font-weight-bold">{{ format(item.stock_value) }}</span>
      </template>

      <template #item.retail_value="{ item }">
        <span class="text-success font-weight-bold">
          {{ format(item.retail_value) }}
        </span>
      </template>

      <template #item.stock_status="{ item }">
        <v-chip
          size="x-small"
          rounded="lg"
          variant="tonal"
          :color="statusColor(item.stock_status)"
          :prepend-icon="statusIcon(item.stock_status)"
        >
          {{ statusLabel(item.stock_status) }}
        </v-chip>
      </template>

      <template #no-data>
        <div class="empty-state">
          <v-icon
            icon="mdi-package-variant-remove"
            size="48"
            color="grey-lighten-2"
          />
          <div class="text-body-2 text-grey mt-2">{{$t('products.empty')}}</div>
        </div>
      </template>
    </v-data-table>
  </div>
</template>

<script setup>
  import { computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useCurrency } from '@/composables/useCurrency_v2.js'

  const { t } = useI18n()
  const { format } = useCurrency()

  defineProps({
    products: { type: Array, default: () => [] },
    search: { type: String, default: '' },
    stockFilter: { type: String, default: null },
    loading: { type: Boolean, default: false }
  })

  const emit = defineEmits(['update:search', 'update:stockFilter'])

  const filterOptions = computed(() => [
    {
      value: 'in_stock',
      label: t('inventory_report.status.in_stock'),
      color: 'success'
    },
    {
      value: 'low_stock',
      label: t('inventory_report.status.low_stock'),
      color: 'warning'
    },
    {
      value: 'out_of_stock',
      label: t('inventory_report.status.out_of_stock'),
      color: 'error'
    }
  ])

  const headers = computed(() => [
    { title: t('inventory_report.table.product'), key: 'name', sortable: true },
    {
      title: t('inventory_report.table.category'),
      key: 'category',
      sortable: true
    },
    {
      title: t('inventory_report.table.stock'),
      key: 'stock_quantity',
      sortable: true
    },
    {
      title: t('inventory_report.table.reorder_at'),
      key: 'reorder_level',
      sortable: true
    },
    {
      title: t('inventory_report.table.cost_price'),
      key: 'cost_price',
      sortable: true
    },
    {
      title: t('inventory_report.table.retail_price'),
      key: 'retail_price',
      sortable: true
    },
    {
      title: t('inventory_report.table.cost_value'),
      key: 'stock_value',
      sortable: true
    },
    {
      title: t('inventory_report.table.retail_value'),
      key: 'retail_value',
      sortable: true
    },
    {
      title: t('inventory_report.table.status'),
      key: 'stock_status',
      sortable: true
    }
  ])

  const statusColor = s =>
    ({ in_stock: 'success', low_stock: 'warning', out_of_stock: 'error' })[s] ??
    'grey'
  const statusIcon = s =>
    ({
      in_stock: 'mdi-check-circle',
      low_stock: 'mdi-alert',
      out_of_stock: 'mdi-close-circle'
    })[s] ?? 'mdi-circle'
  const statusLabel = s =>
    ({
      in_stock: t('inventory_report.status.in_stock'),
      low_stock: t('inventory_report.status.low_stock'),
      out_of_stock: t('inventory_report.status.out_of_stock')
    })[s] ?? s
  const stockClass = s =>
    ({
      in_stock: 'text-success',
      low_stock: 'text-warning',
      out_of_stock: 'text-error'
    })[s] ?? ''
</script>

<style scoped>
  .filter-bar {
    padding: 16px 16px 8px;
  }
  .product-cell {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 0;
  }
  .empty-state {
    text-align: center;
    padding: 40px 0;
  }
  .gap-1 {
    gap: 4px;
  }
</style>
