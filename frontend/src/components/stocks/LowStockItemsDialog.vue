<template>
  <v-dialog v-model="internalModel" max-width="800px">
    <v-card class="bg-background">
      <v-toolbar title="Low stock items" class="bg-warning">
        <v-spacer />
        <v-btn icon="mdi-close" @click="close"></v-btn>
      </v-toolbar>
      <v-card-text>
        <v-data-table
          :items="lowStockItems"
          :headers="tableHeaders"
          class="pa-2"
        />
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, watch, computed } from 'vue'
  import { useDashboardStore } from '@/stores/dashboardStore'

  const dashboardStore = useDashboardStore()

  // Props
  const props = defineProps({
    modelValue: { type: Boolean, required: true }
  })

  // Emits
  const emit = defineEmits(['update:modelValue'])

  // Internal v-model
  const internalModel = ref(props.modelValue)

  // Sync parent → child
  watch(
    () => props.modelValue,
    val => (internalModel.value = val)
  )

  // Sync child → parent
  watch(internalModel, val => {
    emit('update:modelValue', val)
  })

  // Computed low-stock items
  const lowStockItems = computed(() => {
    return dashboardStore.stats?.lowStockItems || []
  })

  // Fetch stats when opening dialog
  watch(internalModel, async val => {
    if (val) {
      await dashboardStore.fetchStats()
    }
  })

  // Close dialog
  function close() {
    internalModel.value = false
  }

  // Table headers
  const tableHeaders = [
    { title: 'Product', key: 'name' },
    { title: 'Stock', key: 'stock' },
    { title: 'Category', key: 'category' }
  ]
</script>
