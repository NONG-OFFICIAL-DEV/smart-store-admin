<template>
  <v-container fluid class="pa-0">
    <AppToolbar
      :title="t('menus.hub.title')"
      :subtitle="t('menus.hub.subtitle')"
    >
      <template v-if="canManage" #actions>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          @click="openCreate"
        >
          {{ activeTab === 'branch-menus' ? t('menus.assign.assign_menu') : t('menus.list.new') }}
        </v-btn>
      </template>
    </AppToolbar>

    <v-tabs v-model="activeTab" color="primary" class="mb-4" show-arrows>
      <v-tab value="menus">{{ t('menu.menus') }}</v-tab>
      <v-tab value="branch-menus">{{ t('menu.branch_menus') }}</v-tab>
    </v-tabs>

    <v-window v-model="activeTab">
      <v-window-item value="menus">
        <MenuManagement ref="menuManagementRef" />
      </v-window-item>
      <v-window-item value="branch-menus">
        <BranchMenu ref="branchMenuRef" />
      </v-window-item>
    </v-window>
  </v-container>
</template>

<script setup>
  import { ref, computed } from 'vue'
  import { useRoute } from 'vue-router'
  import { useI18n } from 'vue-i18n'
  import { usePermission } from '@/composables/usePermission'
  import MenuManagement from '@/views/catalogs/MenuManagement.vue'
  import BranchMenu from '@/views/catalogs/BranchMenu.vue'
  import AppToolbar from '@/components/common/AppToolbar.vue'

  const { t } = useI18n()
  const route = useRoute()
  const { can } = usePermission()

  const canManage = computed(() => can('menus.manage'))

  const requestedTab = typeof route.query.tab === 'string' ? route.query.tab : null
  const activeTab = ref(requestedTab === 'branch-menus' ? 'branch-menus' : 'menus')

  // Each tab owns its own create dialog — this hub just triggers whichever
  // tab is currently active via its exposed openCreate().
  const menuManagementRef = ref(null)
  const branchMenuRef = ref(null)
  const openCreate = () => {
    if (activeTab.value === 'branch-menus') branchMenuRef.value?.openCreate()
    else menuManagementRef.value?.openCreate()
  }
</script>
