<template>
  <v-container fluid class="pa-0">
    <AppToolbar
      :title="t('menus.hub.title')"
      :subtitle="t('menus.hub.subtitle')"
    >
      <template #actions>
        <AppSearch
          v-model="search"
          :placeholder="t('menus.list.search_placeholder')"
        />
        <v-btn
          v-if="canManage"
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          @click="openCreateMenu"
        >
          {{ t('menus.list.new') }}
        </v-btn>
      </template>
    </AppToolbar>

    <MenuCardList
      ref="menuCardListRef"
      :search="search"
      :can-manage="canManage"
    />
  </v-container>
</template>

<script setup>
  import { ref, computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { AppSearch } from '@nong-official-dev/core'
  import { usePermission } from '@/composables/usePermission'
  import AppToolbar from '@/components/common/AppToolbar.vue'
  import MenuCardList from '@/components/catalogs/MenuCardList.vue'

  const { t } = useI18n()
  const { can } = usePermission()

  const canManage = computed(() => can('menus.manage'))

  const search = ref('')
  const menuCardListRef = ref(null)
  const openCreateMenu = () => menuCardListRef.value?.openCreateMenu()
</script>
