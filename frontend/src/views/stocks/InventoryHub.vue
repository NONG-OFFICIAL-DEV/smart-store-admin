<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-warehouse"
      :title="t('inventory_hub.title')"
      :subtitle="t('inventory_hub.subtitle')"
    />

    <v-tabs v-model="activeTab" color="primary" class="mb-4" show-arrows>
      <v-tab v-for="tab in tabs" :key="tab.key" :value="tab.key">
        {{ tab.label }}
      </v-tab>
    </v-tabs>

    <v-window v-model="activeTab">
      <v-window-item value="stock">
        <StockManagement v-if="authStore.isFood" />
        <MartStockManagement v-else />
      </v-window-item>
      <v-window-item value="purchase-orders">
        <PurchaseManagement v-if="authStore.isFood" />
        <MartPurchaseOrder v-else />
      </v-window-item>
      <v-window-item value="suppliers"><SupplierManagement /></v-window-item>
    </v-window>
  </v-container>
</template>

<script setup>
  import { ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRoute } from 'vue-router'
  import { useAuthStore } from '@/stores/authStore'
  import StockManagement from '@/views/stocks/StockManagement.vue'
  import MartStockManagement from '@/views/mart/MartStockManagement.vue'
  import PurchaseManagement from '@/views/stocks/PurchaseManagement.vue'
  import MartPurchaseOrder from '@/views/mart/MartPurchaseOrder.vue'
  import SupplierManagement from '@/views/stocks/SupplierManagement.vue'

  const { t } = useI18n()
  const route = useRoute()
  const authStore = useAuthStore()

  const tabs = [
    { key: 'stock', label: t('inventory_hub.tabs.stock') },
    { key: 'purchase-orders', label: t('inventory_hub.tabs.purchase_orders') },
    { key: 'suppliers', label: t('inventory_hub.tabs.suppliers') }
  ]

  const requestedTab = typeof route.query.tab === 'string' ? route.query.tab : null
  const activeTab = ref(tabs.find(tb => tb.key === requestedTab)?.key ?? tabs[0].key)
</script>
