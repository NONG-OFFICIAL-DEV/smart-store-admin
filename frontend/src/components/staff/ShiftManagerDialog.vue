<template>
  <AppDialog
    :model-value="modelValue"
    :title="$t('shifts.title')"
    :max-width="900"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <ShiftManagement @assign-staff="$emit('assign-staff', $event)" />

    <template #actions>
      <v-btn variant="tonal" rounded="lg" @click="$emit('update:modelValue', false)">
        {{ $t('btn.close') }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
  import { AppDialog } from '@nong-official-dev/core'
  import ShiftManagement from '@/views/staff/ShiftManagement.vue'

  defineProps({
    modelValue: { type: Boolean, default: false }
  })

  defineEmits(['update:modelValue', 'assign-staff'])
</script>
