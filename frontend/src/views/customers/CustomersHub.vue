<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-account-group-outline"
      :title="t('customers_hub.title')"
      :subtitle="t('customers_hub.subtitle')"
    />

    <v-tabs v-model="activeTab" color="primary" class="mb-4" show-arrows>
      <v-tab v-for="tab in visibleTabs" :key="tab.key" :value="tab.key">
        {{ tab.label }}
      </v-tab>
    </v-tabs>

    <v-window v-model="activeTab">
      <v-window-item value="customers"><CustomerList /></v-window-item>
      <v-window-item value="loyalty"><Loyalty /></v-window-item>
    </v-window>
  </v-container>
</template>

<script setup>
  import { ref, computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRoute } from 'vue-router'
  import { useAuthStore } from '@/stores/authStore'
  import CustomerList from '@/views/customers/CustomerList.vue'
  import Loyalty from '@/views/loyalty/Loyalty.vue'

  const { t } = useI18n()
  const route = useRoute()
  const authStore = useAuthStore()

  const allTabs = [
    { key: 'customers', label: t('customers_hub.tabs.customers') },
    { key: 'loyalty', label: t('customers_hub.tabs.loyalty'), proOnly: true }
  ]

  const visibleTabs = computed(() => allTabs.filter(tab => !tab.proOnly || authStore.hasPlan('pro')))

  const requestedTab = typeof route.query.tab === 'string' ? route.query.tab : null
  const activeTab = ref(
    visibleTabs.value.find(tb => tb.key === requestedTab)?.key ?? visibleTabs.value[0]?.key
  )
</script>
