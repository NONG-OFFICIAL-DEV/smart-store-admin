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
          @click="item.routeName ? router.push({ name: item.routeName }) : openLink(item.url)"
        >
          <v-chip
            v-if="item.badge"
            size="x-small"
            color="success"
            variant="flat"
            class="pos-operation-badge"
          >
            {{ item.badge }}
          </v-chip>
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
  import { useRouter } from 'vue-router'
  import { useAuthStore } from '@/stores/authStore'
  import { BU_CATEGORIES } from '@/constants/businessTypes'
  import { useI18n } from 'vue-i18n'

  const authStore = useAuthStore()
  const router = useRouter()
  const { t } = useI18n()

  // ─── Operation registry ───────────────────────────────────────────────────────
  // `buTypes` lists every code from businessTypes.js that can access this POS.
  // Entries with `routeName` navigate internally (router.push); entries with
  // `url` open the external app in a new tab — the two are independent, both
  // kinds can coexist so tenants keep the external option during rollout.
  // To add a new operation: add an entry here.
  // To give a new business type access to an existing POS: update businessTypes.js only.
  const operations = computed(() => [
    {
      key: 'quick-sale-food',
      title: t('operation.quick_sale.food_title'),
      subtitle: t('operation.quick_sale.food_subtitle'),
      icon: 'mdi-lightning-bolt-outline',
      routeName: 'pos-food',
      badge: t('operation.quick_sale.badge'),
      buTypes: BU_CATEGORIES.food
    },
    {
      key: 'quick-sale-retail',
      title: t('operation.quick_sale.retail_title'),
      subtitle: t('operation.quick_sale.retail_subtitle'),
      icon: 'mdi-lightning-bolt-outline',
      routeName: 'pos-retail',
      badge: t('operation.quick_sale.badge'),
      buTypes: BU_CATEGORIES.mart
    },
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
    operations.value.filter(item => {
      if (!authStore.isSuperAdmin && !item.buTypes?.has(authStore.bu_type)) return false
      if (item.routeName) return authStore.can('orders.manage')
      return true
    })
  )

  function openLink(url) {
    if (!url) return
    window.open(url, '_blank')
  }
</script>

<style scoped>
  .v-card {
    position: relative;
  }
  .pos-operation-badge {
    position: absolute;
    top: 8px;
    right: 8px;
  }
</style>
