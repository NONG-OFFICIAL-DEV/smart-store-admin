<template>
  <div>
    <h3>Low Stock Recommendations</h3>
    <div v-if="inventoryAIStore.loading.lowStock">Loading...</div>
    <pre v-else>{{ inventoryAIStore.lowStock }}</pre>

    <h3>Purchase Insights</h3>
    <div v-if="inventoryAIStore.loading.purchase">Loading...</div>
    <pre v-else>{{ inventoryAIStore.purchase }}</pre>

    <!-- <h3>Stock Movement Insights</h3>
    <div v-if="inventoryAIStore.loading.movement">Loading...</div>
    <pre v-else>{{ inventoryAIStore.movement }}</pre> -->
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useInventoryAIStore } from '@/stores/inventoryAIStore'

const inventoryAIStore = useInventoryAIStore()

onMounted(async () => {
  await inventoryAIStore.fetchLowStock()
  await inventoryAIStore.fetchPurchase(new Date())
//   await inventoryAIStore.fetchMovement()
})
</script>
