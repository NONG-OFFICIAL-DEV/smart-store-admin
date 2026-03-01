<template>
  <v-dialog v-model="dialogVisible" max-width="900px">
    <v-card rounded="lg" elevation="0" class="bg-background">
      <!-- Header -->
      <v-card-title class="d-flex align-center bg-primary">
        Stock Movement History
        <v-spacer />
        <v-btn icon variant="text" @click="close">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </v-card-title>

      <v-divider />

      <v-card-text>
        <!-- Empty State -->
        <v-alert
          v-if="!props.stock"
          type="warning"
          variant="tonal"
          class="mb-4"
        >
          No stock selected. Please choose an item to view movement history.
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
              No movement history found.
            </v-alert>
          </template>
        </v-data-table>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn color="primary" variant="flat" @click="close">Close</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { watch, computed } from 'vue'
  import { useStockMovementStore } from '@/stores/stockMovementStore'
  import { useDate } from '@/composables/useDate'
  import { useCurrency } from '@/composables/useCurrency.js'

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

  const headers = [
    { title: 'Type', key: 'movement_type', sortable: false },
    { title: 'Qty', key: 'qty' },
    { title: 'Total cost', key: 'total_cost' },
    { title: 'Created By', key: 'user_name' },
    { title: 'Date', key: 'created_at' }
  ]

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
