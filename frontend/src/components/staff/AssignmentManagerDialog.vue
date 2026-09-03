<template>
  <AppDialog
    :model-value="modelValue"
    :title="$t('shift_assignments.title')"
    :max-width="1100"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <ShiftAssignment :preset-shift-id="presetShiftId" />

    <template #actions="{ loading }">
      <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="$emit('update:modelValue', false)">
        {{ $t('btn.close') }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
  import { AppDialog } from '@nong-official-dev/core'
  import ShiftAssignment from '@/views/staff/ShiftAssignment.vue'

  defineProps({
    modelValue: { type: Boolean, default: false },
    presetShiftId: { type: String, default: null }
  })

  defineEmits(['update:modelValue'])
</script>
