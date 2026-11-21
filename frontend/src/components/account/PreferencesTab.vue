<template>
  <div class="d-flex flex-column gap-6">
    <!-- Language -->
    <v-card variant="outlined" rounded="lg">
      <v-card-text class="pa-5">
        <div class="d-flex align-center gap-2 mb-4">
          <v-icon size="18" color="primary">mdi-translate</v-icon>
          <span class="text-subtitle-2 font-weight-bold">{{ t('preferences.language') }}</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <v-btn
            v-for="lang in languages"
            :key="lang.code"
            :variant="locale === lang.code ? 'tonal' : 'outlined'"
            :color="locale === lang.code ? 'primary' : 'default'"
            rounded="lg"
            size="small"
            class="text-none lang-btn"
            @click="changeLang(lang.code)"
          >
            <v-img :src="lang.flag" width="18" height="18" class="flag-img me-2" cover />
            {{ lang.label }}
            <v-icon v-if="locale === lang.code" end size="12">mdi-check</v-icon>
          </v-btn>
        </div>
      </v-card-text>
    </v-card>

    <!-- Appearance -->
    <v-card variant="outlined" rounded="lg" class="mt-4">
      <v-card-text class="pa-5">
        <div class="d-flex align-center gap-2 mb-4">
          <v-icon size="18" color="primary">mdi-tune-variant</v-icon>
          <span class="text-subtitle-2 font-weight-bold">{{ t('app_bar.appearance') }}</span>
        </div>
        <AppearanceSettings />
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
  import { computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import AppearanceSettings from './AppearanceSettings.vue'
  import authService from '@/api/auth'

  const { t, locale } = useI18n()

  const languages = computed(() => [
    { code: 'km', label: t('lang.km') || 'ខ្មែរ', flag: '/images/kh.png' },
    { code: 'en', label: t('lang.en') || 'English', flag: '/images/gb.png' }
  ])

  function changeLang(code) {
    locale.value = code
    localStorage.setItem('lang', code)
    authService.updatePreferences({ preferred_language: code }).catch(() => {})
  }
</script>

<style scoped>
  .lang-btn {
    min-width: 110px;
  }
  .flag-img {
    border-radius: 3px;
    flex-shrink: 0;
  }
</style>
