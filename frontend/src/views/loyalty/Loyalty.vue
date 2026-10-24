<template>
  <v-container fluid class="pa-0">
    <AppToolbar
      :title="t('promotions.title')"
      :subtitle="t('promotions.subtitle')"
    >
      <template #actions>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          @click="openCreatePromotion"
        >
          {{ t('promotions.list.new') }}
        </v-btn>
      </template>
    </AppToolbar>
    <!-- <AppSearch
      v-model="search"
      :placeholder="t('promotions.list.search_placeholder')"
    /> -->
    <PromotionCardList ref="cardListRef" :search="search" />
  </v-container>
</template>

<script setup>
  import { ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { AppSearch } from '@nong-official-dev/core'
  import AppToolbar from '@/components/common/AppToolbar.vue'
  import PromotionCardList from '@/components/loyalty/PromotionCardList.vue'

  const { t } = useI18n()

  const search = ref('')
  const cardListRef = ref(null)
  const openCreatePromotion = () => cardListRef.value?.openCreatePromotion()
</script>
