<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-store"
      :title="$t('menu.operation')"
    ></custom-title>
    <v-row>
      <v-col
        v-for="item in visibleOperations"
        :key="item.key"
        cols="12" sm="6" md="4" lg="3"
      >
        <v-card
          class="pa-4 text-center"
          hover
          rounded="lg"
          @click="openLink(item.url)"
        >
          <v-icon size="48" color="primary">{{ item.icon }}</v-icon>
          <v-card-title class="justify-center">{{ item.title }}</v-card-title>
          <v-card-subtitle>{{ item.subtitle }}</v-card-subtitle>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@/stores/authStore'

const authStore = useAuthStore()

const isSuperAdmin = computed(() => authStore.isSuperAdmin)
const buType       = computed(() => authStore.bu_type)

const operations = [
  {
    key:      'pos-retail',
    title:    'Retail POS',
    subtitle: 'Open retail POS system',
    icon:     'mdi-cash-register',
    url:      'https://retail.nongofficial.store',
    buTypes:  ['minimart', 'retail', 'wholesale'], // shown to these bu_types (+ super admin)
  },
  {
    key:      'pos-food',
    title:    'Food & Cafe POS',
    subtitle: 'Open food/cafe POS system',
    icon:     'mdi-coffee',
    url:      'https://coffee-pos.nongofficial.store',
    buTypes:  ['restaurant', 'cafe', 'bakery', 'kiosk', 'food_truck'],
  }
]

const visibleOperations = computed(() =>
  operations.filter(item => {
    // Super admin sees everything
    if (isSuperAdmin.value) return true
    // Others see only items matching their bu_type
    return item.buTypes.includes(buType.value)
  })
)

function openLink(url) {
  if (!url) return
  window.open(url, '_blank')
}
</script>