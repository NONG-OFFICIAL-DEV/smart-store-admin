<template>
  <StockManagement v-if="authStore.isFood" />
  <MartStockManagement v-else />
</template>

<script setup>
  import { useAuthStore } from '@/stores/authStore'
  import StockManagement from '@/views/stocks/StockManagement.vue'
  import MartStockManagement from '@/views/mart/MartStockManagement.vue'

  const authStore = useAuthStore()
</script>
