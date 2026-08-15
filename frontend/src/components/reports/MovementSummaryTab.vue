<template>
  <div class="pa-4">
    <!-- Period pill -->
    <div class="period-row">
      <v-icon icon="mdi-calendar-range" color="primary" size="16" />
      <span class="text-body-2 text-medium-emphasis">
        {{ t('common.period', 'Period') }}:
      </span>
      <v-chip
        v-if="dateFrom && dateTo"
        size="small"
        color="primary"
        variant="tonal"
        rounded="pill"
      >
        {{ fmtDate(dateFrom) }} — {{ fmtDate(dateTo) }}
      </v-chip>
      <v-chip v-else size="small" color="grey" variant="tonal" rounded="pill">
        {{ t('inventory_report.movement.select_range', 'Select a date range') }}
      </v-chip>
    </div>

    <!-- Movement cards -->
    <v-row v-if="movements.length" dense class="mb-4">
      <v-col
        v-for="m in movements"
        :key="m.movement_type"
        cols="12"
        sm="6"
        md="4"
      >
        <v-card rounded="xl" border elevation="0" class="pa-3 movement-card">
          <div class="movement-row">
            <v-avatar
              size="44"
              :color="movementColor(m.movement_type)"
              variant="tonal"
              rounded="lg"
              class="flex-shrink-0"
            >
              <v-icon :icon="movementIcon(m.movement_type)" size="22" />
            </v-avatar>
            <div class="movement-info">
              <div class="text-caption text-medium-emphasis">
                {{ movementLabel(m.movement_type) }}
              </div>
              <div class="text-body-1 font-weight-black">
                {{ Number(m.total_qty).toLocaleString() }}
                <span
                  class="text-caption text-medium-emphasis font-weight-regular"
                >
                  units
                </span>
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ m.count }}
                {{ t('inventory_report.movement.transactions', 'txns') }}
              </div>
            </div>
            <div v-if="Number(m.total_value) > 0" class="flex-shrink-0">
              <v-chip
                size="small"
                :color="movementColor(m.movement_type)"
                variant="tonal"
                rounded="lg"
              >
                {{ format(m.total_value) }}
              </v-chip>
            </div>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Empty state -->
    <div v-if="!movements.length" class="empty-state">
      <v-icon icon="mdi-calendar-range" size="48" color="grey-lighten-2" />
      <div class="text-body-2 text-medium-emphasis mt-2">
        {{
          t(
            'inventory_report.movement.empty',
            'Select a date range to see stock movements'
          )
        }}
      </div>
    </div>

    <!-- Top movers table -->
    <v-card v-if="productMovements.length" rounded="xl" border elevation="0">
      <div class="table-header">
        <span class="text-body-2 font-weight-bold">
          {{ t('inventory_report.movement.top_move', 'Top Movers') }}
        </span>
        <v-chip size="small" color="primary" variant="tonal" rounded="pill">
          {{ productMovements.length }} products
        </v-chip>
      </div>
      <v-divider />
      <v-data-table
        :headers="headers"
        :items="productMovements"
        :items-per-page="10"
        density="compact"
        item-value="product_id"
      >
        <template #item.product_name="{ item }">
          <div class="product-cell">
            <v-avatar
              size="30"
              rounded="lg"
              class="bg-grey-lighten-4 border flex-shrink-0"
            >
              <v-img v-if="item.image_url" :src="item.image_url" cover />
              <v-icon
                v-else
                icon="mdi-package-variant"
                size="14"
                color="grey"
              />
            </v-avatar>
            <span class="text-body-2 font-weight-medium">
              {{ item.product_name }}
            </span>
          </div>
        </template>
        <template #item.total_in="{ item }">
          <span class="text-success font-weight-bold">
            +{{ item.total_in }}
          </span>
        </template>
        <template #item.total_out="{ item }">
          <span class="text-error font-weight-bold">-{{ item.total_out }}</span>
        </template>
        <template #item.net="{ item }">
          <span
            :class="
              item.total_in - item.total_out >= 0
                ? 'text-success'
                : 'text-error'
            "
            class="font-weight-bold"
          >
            {{ item.total_in - item.total_out >= 0 ? '+' : ''
            }}{{ item.total_in - item.total_out }}
          </span>
        </template>
      </v-data-table>
    </v-card>
  </div>
</template>

<script setup>
  import { computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import { useDate } from '@/composables/useDate'

  const { t } = useI18n()
  const { format } = useCurrency()
  const { formatShortDate: fmtDate } = useDate()

  defineProps({
    movements: { type: Array, default: () => [] },
    productMovements: { type: Array, default: () => [] },
    dateFrom: { type: String, default: '' },
    dateTo: { type: String, default: '' }
  })

  const headers = computed(() => [
    {
      title: t('inventory_report.table.product'),
      key: 'product_name',
      sortable: false
    },
    {
      title: t('inventory_report.top_movers.in'),
      key: 'total_in',
      sortable: true
    },
    {
      title: t('inventory_report.top_movers.out'),
      key: 'total_out',
      sortable: true
    },
    { title: t('inventory_report.top_movers.net'), key: 'net', sortable: false }
  ])


  const movementIcon = type =>
    ({
      purchase: 'mdi-package-down',
      sale: 'mdi-cart-outline',
      adjustment_in: 'mdi-plus-circle-outline',
      adjustment_out: 'mdi-minus-circle-outline',
      waste: 'mdi-trash-can-outline',
      count: 'mdi-clipboard-check-outline'
    })[type] ?? 'mdi-circle'

  const movementColor = type =>
    ({
      purchase: 'success',
      sale: 'primary',
      adjustment_in: 'teal',
      adjustment_out: 'orange',
      waste: 'error',
      count: 'purple'
    })[type] ?? 'grey'

  const movementLabel = type => t(`inventory_report.movement.${type}`, type)
</script>

<style scoped>
  .period-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
  }
  .movement-card {
    transition: transform 0.15s ease;
  }
  .movement-card:hover {
    transform: translateY(-2px);
  }
  .movement-row {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .movement-info {
    flex: 1;
    min-width: 0;
  }
  .empty-state {
    text-align: center;
    padding: 48px 0;
  }
  .table-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 16px 12px;
  }
  .product-cell {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 0;
  }
</style>
