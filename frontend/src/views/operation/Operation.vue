<template>
  <v-container fluid class="pa-0">
    <custom-title icon="mdi-store" :title="$t('menu.operation')" />

    <v-row>
      <v-col
        v-for="item in visibleOperations"
        :key="item.key"
        cols="12"
        sm="6"
        md="4"
        lg="3"
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
  import { BU_CATEGORIES } from '@/constants/businessTypes'
  import { useI18n } from 'vue-i18n'

  const authStore = useAuthStore()
  const { t } = useI18n()

  // ─── Operation registry ───────────────────────────────────────────────────────
  // `buTypes` lists every code from businessTypes.js that can access this POS.
  // To add a new operation: add an entry here.
  // To give a new business type access to an existing POS: update businessTypes.js only.
  const operations = computed(() => [
    {
      key: 'pos-food',
      title: t('operation.pos_food_title'),
      subtitle: t('operation.pos_food_subtitle'),
      icon: 'mdi-coffee',
      url: 'https://pos-cafe.nexstacktech.com/',
      buTypes: BU_CATEGORIES.food
    },
    {
      key: 'pos-retail',
      title: t('operation.pos_retail_title'),
      subtitle: t('operation.pos_retail_subtitle'),
      icon: 'mdi-cash-register',
      url: 'https://pos-retail.nexstacktech.com/',
      buTypes: BU_CATEGORIES.mart
    }
  ])

  const visibleOperations = computed(() =>
    operations.value.filter(
      item => authStore.isSuperAdmin || item.buTypes?.has(authStore.bu_type)
    )
  )

  function openLink(url) {
    if (!url) return
    window.open(url, '_blank')
  }
</script>
