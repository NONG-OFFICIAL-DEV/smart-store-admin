<template>
  <AppDialog
    :model-value="modelValue"
    :title="$t('menu.modifiers')"
    :max-width="1100"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <ProductModifierGroup />

    <template #actions="{ loading }">
      <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="$emit('update:modelValue', false)">{{ $t('btn.close') }}</v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
  import { AppDialog } from '@nong-official-dev/core'
  import ProductModifierGroup from '@/views/products/ProductModifierGroup.vue'

  defineProps({
    modelValue: { type: Boolean, default: false }
  })

  defineEmits(['update:modelValue'])
</script>
