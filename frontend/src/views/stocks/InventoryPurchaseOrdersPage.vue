<template>
  <PurchaseManagement v-if="authStore.isFood" />
  <MartPurchaseOrder v-else />
</template>

<script setup>
  import { useAuthStore } from '@/stores/authStore'
  import PurchaseManagement from '@/views/stocks/PurchaseManagement.vue'
  import MartPurchaseOrder from '@/views/mart/MartPurchaseOrder.vue'

  const authStore = useAuthStore()
</script>
