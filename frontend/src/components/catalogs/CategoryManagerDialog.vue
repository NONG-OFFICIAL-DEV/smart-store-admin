<template>
  <AppDialog
    :model-value="modelValue"
    :title="$t('menu.categories')"
    :max-width="640"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <CategoryView />

    <template #actions="{ loading }">
      <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="$emit('update:modelValue', false)">{{ $t('btn.close') }}</v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
  import { AppDialog } from '@nong-official-dev/core'
  import CategoryView from '@/views/catalogs/CategoryView.vue'

  defineProps({
    modelValue: { type: Boolean, default: false }
  })

  defineEmits(['update:modelValue'])
</script>
