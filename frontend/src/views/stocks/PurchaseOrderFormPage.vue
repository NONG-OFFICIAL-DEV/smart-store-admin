<template>
  <FoodPoForm v-if="authStore.isFood" />
  <MartPoForm v-else />
</template>

<script setup>
  import { useAuthStore } from '@/stores/authStore'
  import FoodPoForm from '@/views/stocks/FoodPoForm.vue'
  import MartPoForm from '@/views/mart/MartPoForm.vue'

  const authStore = useAuthStore()
</script>
