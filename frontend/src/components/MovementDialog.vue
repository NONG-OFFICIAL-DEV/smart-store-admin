<template>
  <AppDialog
    v-model="dialogVisible"
    :max-width="900"
    :title="$t('stock_movements.dialog_title')"
    icon="mdi-swap-vertical-bold"
    color="primary"
    :hide-submit="true"
    :cancel-text="$t('btn.close')"
    @close="close"
  >
    <!-- Empty State -->
    <v-alert
      v-if="!props.stock"
      type="warning"
      variant="tonal"
      class="mb-4"
    >
      {{ $t('stock_movements.no_stock_selected') }}
    </v-alert>
    <!-- Data Table -->

    <v-data-table
      v-if="props.stock"
      :headers="headers"
      :items="stockMovementStore.movements"
      :loading="stockMovementStore.loading"
      density="compact"
      class="elevation-0"
    >
      <template #item.movement_type="{ item }">
        <v-chip :color="typeColor(item.movement_type)" size="small" label>
          {{ item.movement_type }}
        </v-chip>
      </template>

      <template #item.qty="{ item }">
        <span :class="item.qty < 0 ? 'text-red' : 'text-green'">
          {{ item.qty }}
        </span>
      </template>
      <template #item.total_cost="{ item }">
        {{formatCurrency(item.total_cost)}}
      </template>

      <template #item.created_at="{ item }">
        {{ formatDateTime(item.created_at) }}
      </template>

      <!-- Loading Skeleton -->
      <template #loading>
        <v-skeleton-loader
          type="table-row-divider, table-row, table-row, table-row"
        />
      </template>

      <!-- No Data -->
      <template #no-data>
        <v-alert type="info" variant="tonal">
          {{ $t('stock_movements.empty') }}
        </v-alert>
      </template>
    </v-data-table>
  </AppDialog>
</template>

<script setup>
  import { watch, computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useStockMovementStore } from '@/stores/stockMovementStore'
  import { useDate } from '@/composables/useDate'
  import { useCurrency } from '@/composables/useCurrency.js'
  import AppDialog from '@/components/common/AppDialog.vue'

  const { t } = useI18n()
  const { formatCurrency } = useCurrency()
  const { formatDateTime } = useDate()
  const stockMovementStore = useStockMovementStore()

  const props = defineProps({
    modelValue: Boolean,
    stock: Object
  })

  const emit = defineEmits(['update:modelValue'])

  const dialogVisible = computed({
    get: () => props.modelValue,
    set: val => emit('update:modelValue', val)
  })

  const headers = computed(() => [
    { title: t('stock_movements.table.type'), key: 'movement_type', sortable: false },
    { title: t('stock_movements.table.qty'), key: 'qty' },
    { title: t('stock_movements.table.total_cost'), key: 'total_cost' },
    { title: t('stock_movements.table.staff'), key: 'user_name' },
    { title: t('stock_movements.table.date'), key: 'created_at' }
  ])

  watch(dialogVisible, async val => {
    if (val && props.stock) {
      await stockMovementStore.fetchMovements(props.stock.product_id)
    }
  })
  const typeColor = type => {
    switch (type) {
      case 'purchase':
        return 'green'
      case 'sale':
        return 'red'
      case 'adjustment':
        return 'orange'
      case 'transfer_in':
        return 'blue'
      case 'transfer_out':
        return 'deep-purple'
      case 'return':
        return 'teal'
      case 'loss':
        return 'brown'
      default:
        return 'grey'
    }
  }

  function close() {
    emit('update:modelValue', false)
  }
</script>

<style scoped>
  .text-green {
    color: #2e7d32;
  }
  .text-red {
    color: #c62828;
  }
</style>
