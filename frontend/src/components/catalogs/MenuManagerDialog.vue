<template>
  <AppDialog
    :model-value="modelValue"
    :title="$t('menu.menus')"
    :max-width="1100"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <v-tabs v-model="tab" color="primary" class="px-4">
      <v-tab value="menus">{{ $t('menu.menus') }}</v-tab>
      <v-tab value="branch-menus">{{ $t('menu.branch_menus') }}</v-tab>
    </v-tabs>
    <v-divider />
    <v-window v-model="tab" class="pa-4">
      <v-window-item value="menus">
        <MenuManagement />
      </v-window-item>
      <v-window-item value="branch-menus">
        <BranchMenu />
      </v-window-item>
    </v-window>

    <template #actions="{ loading }">
      <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="$emit('update:modelValue', false)">
        {{ $t('btn.close') }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
  import { ref } from 'vue'
  import { AppDialog } from '@nong-official-dev/core'
  import MenuManagement from '@/views/catalogs/MenuManagement.vue'
  import BranchMenu from '@/views/catalogs/BranchMenu.vue'

  defineProps({
    modelValue: { type: Boolean, default: false }
  })

  defineEmits(['update:modelValue'])

  const tab = ref('menus')
</script>
