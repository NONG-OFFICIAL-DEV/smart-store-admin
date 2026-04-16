<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-store"
      :title="$t('menu.operation')"
    ></custom-title>
    <!-- Operation Cards / Links -->
    <v-row>
      <v-col
        v-for="item in operations"
        :key="item.title"
        cols="12" sm="6" md="4" lg="3"
      >
        <v-card
          class="pa-4 text-center"
          hover
          rounded="log"
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
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/authStore'

const { t } = useI18n()
const auth = useAuthStore()

// Map bu_type → POS URL
const posUrlMap = {
  // Mart types → retail POS
  minimart:   'https://retail.nongofficial.store',
  retail:     'https://retail.nongofficial.store',
  wholesale:  'https://retail.nongofficial.store',

  // Food types → coffee/food POS
  restaurant: 'https://coffee-pos.nongofficial.store',
  cafe:       'https://coffee-pos.nongofficial.store',
  bakery:     'https://coffee-pos.nongofficial.store',
  kiosk:      'https://coffee-pos.nongofficial.store',
  food_truck: 'https://coffee-pos.nongofficial.store',
}

const posUrl = computed(() => posUrlMap[auth.bu_type] ?? null)

const operations = computed(() => {
  const items = []

  if (posUrl.value) {
    items.push({
      title: t('menu.pos'),
      subtitle: auth.isMart ? 'Retail Point of Sale' : 'Food & Beverage POS',
      icon: auth.isMart ? 'mdi-cash-register' : 'mdi-coffee-outline',
      url: posUrl.value
    })
  }

  // Add more operations here if needed
  // items.push({ title: '...', subtitle: '...', icon: '...', url: '...' })

  return items
})

function openLink(url) {
  if (!url) return
  window.open(url, '_blank')
}
</script>