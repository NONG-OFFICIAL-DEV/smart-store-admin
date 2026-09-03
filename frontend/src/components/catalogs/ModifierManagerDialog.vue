<template>
  <AppDialog
    :model-value="modelValue"
    :title="$t('menu.modifiers')"
    icon="mdi-tune-variant"
    :max-width="1100"
    hide-submit
    :cancel-text="$t('btn.close')"
    body-class="pa-4"
    @update:model-value="$emit('update:modelValue', $event)"
    @close="$emit('update:modelValue', false)"
  >
    <ProductModifierGroup />
  </AppDialog>
</template>

<script setup>
  import AppDialog from '@/components/common/AppDialog.vue'
  import ProductModifierGroup from '@/views/products/ProductModifierGroup.vue'

  defineProps({
    modelValue: { type: Boolean, default: false }
  })

  defineEmits(['update:modelValue'])
</script>
