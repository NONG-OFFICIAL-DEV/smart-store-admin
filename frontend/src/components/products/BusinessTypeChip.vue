<template>
  <div class="d-flex align-center" style="gap:10px">
    <v-avatar
      :color="config.color"
      size="36"
      rounded="lg"
      variant="tonal"
    >
      <v-icon :icon="config.icon" size="18" />
    </v-avatar>
    <div>
      <div class="text-body-2 font-weight-bold">{{ label }}</div>
      <v-chip
        :color="nature === 'food' ? 'orange' : 'indigo'"
        variant="tonal"
        size="x-small"
        rounded="lg"
        class="mt-1"
        :prepend-icon="nature === 'food' ? 'mdi-silverware-fork-knife' : 'mdi-shopping-outline'"
      >
        {{ nature === 'food' ? $t('products.businessNature.food') : $t('products.businessNature.mart') }}
      </v-chip>
    </div>
  </div>
</template>

<script setup>
defineProps({
  config:  { type: Object,  required: true },
  label:   { type: String,  required: true },
  nature:  { type: String,  default: 'food' }, // 'food' | 'mart'
})
</script>