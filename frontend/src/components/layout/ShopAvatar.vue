<template>
  <v-avatar :size="size" rounded="lg" :color="logoUrl ? undefined : 'primary'">
    <v-img v-if="logoUrl" :src="logoUrl" cover />
    <span v-else class="text-body-small font-weight-bold text-white">{{ initial }}</span>
  </v-avatar>
</template>

<script setup>
  import { computed } from 'vue'

  const props = defineProps({
    logoUrl: { type: String, default: null },
    name: { type: String, default: '' },
    size: { type: [Number, String], default: 30 }
  })

  const initial = computed(() => props.name?.trim()?.charAt(0)?.toUpperCase() || '?')
</script>
