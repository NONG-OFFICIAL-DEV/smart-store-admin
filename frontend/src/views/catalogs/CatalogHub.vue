<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-tag-multiple-outline"
      :title="t('catalog_hub.title')"
      :subtitle="t('catalog_hub.subtitle')"
    />

    <v-tabs v-model="activeTab" color="primary" class="mb-4" show-arrows>
      <v-tab v-for="tab in visibleTabs" :key="tab.key" :value="tab.key">
        {{ tab.label }}
      </v-tab>
    </v-tabs>

    <v-window v-model="activeTab">
      <v-window-item value="products"><ProductManagement /></v-window-item>
      <v-window-item value="categories"><CategoryView /></v-window-item>
      <v-window-item value="modifiers"><ProductModifierGroup /></v-window-item>
      <v-window-item value="menus"><MenuManagement /></v-window-item>
      <v-window-item value="branch-menus"><BranchMenu /></v-window-item>
      <v-window-item value="ingredients"><Ingredient /></v-window-item>
    </v-window>
  </v-container>
</template>

<script setup>
  import { ref, computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRoute } from 'vue-router'
  import { useAuthStore } from '@/stores/authStore'
  import ProductManagement from '@/views/products/ProductManagement.vue'
  import CategoryView from '@/views/catalogs/CategoryView.vue'
  import ProductModifierGroup from '@/views/products/ProductModifierGroup.vue'
  import MenuManagement from '@/views/catalogs/MenuManagement.vue'
  import BranchMenu from '@/views/catalogs/BranchMenu.vue'
  import Ingredient from '@/views/ingredients/Ingredient.vue'

  const { t } = useI18n()
  const route = useRoute()
  const authStore = useAuthStore()

  const allTabs = [
    { key: 'products', label: t('catalog_hub.tabs.products') },
    { key: 'categories', label: t('catalog_hub.tabs.categories') },
    { key: 'modifiers', label: t('catalog_hub.tabs.modifiers'), foodOnly: true },
    { key: 'menus', label: t('catalog_hub.tabs.menus'), foodOnly: true },
    { key: 'branch-menus', label: t('catalog_hub.tabs.branch_menus'), foodOnly: true },
    { key: 'ingredients', label: t('catalog_hub.tabs.ingredients'), foodOnly: true }
  ]

  const visibleTabs = computed(() => allTabs.filter(tab => !tab.foodOnly || authStore.isFood))

  const requestedTab = typeof route.query.tab === 'string' ? route.query.tab : null
  const activeTab = ref(
    visibleTabs.value.find(tb => tb.key === requestedTab)?.key ?? visibleTabs.value[0]?.key
  )
</script>
