<template>
  <AppDialog
    :model-value="modelValue"
    :title="$t('menu.categories')"
    :max-width="640"
    hide-submit
    :cancel-text="$t('btn.close')"
    body-class="pa-4"
    @update:model-value="$emit('update:modelValue', $event)"
    @close="$emit('update:modelValue', false)"
  >
    <CategoryView />
  </AppDialog>
</template>

<script setup>
  import AppDialog from '@/components/common/AppDialog.vue'
  import CategoryView from '@/views/catalogs/CategoryView.vue'

  defineProps({
    modelValue: { type: Boolean, default: false }
  })

  defineEmits(['update:modelValue'])
</script>
