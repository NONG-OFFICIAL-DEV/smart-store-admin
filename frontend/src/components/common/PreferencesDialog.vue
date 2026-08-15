<template>
  <AppDialog
    v-model="model"
    :max-width="440"
    :persistent="false"
    :title="t('preferences.title')"
    :subtitle="t('preferences.subtitle')"
    icon="mdi-tune-variant"
    color="primary"
    :hide-submit="true"
    :cancel-text="t('btn.done')"
    body-class="pa-0"
  >
        <!-- Language -->
        <div class="px-5 py-4">
          <div class="d-flex align-center gap-2 mb-3">
            <v-icon size="16" color="primary">mdi-translate</v-icon>
            <span class="section-label">{{ t('preferences.language') }}</span>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <v-btn
              v-for="lang in languages"
              :key="lang.code"
              :variant="selectedLang === lang.code ? 'tonal' : 'outlined'"
              :color="selectedLang === lang.code ? 'primary' : 'default'"
              rounded="lg"
              size="small"
              class="text-none lang-btn"
              @click="changeLang(lang.code)"
            >
              <img :src="lang.imgSrc" :alt="lang.alt" class="flag-img me-2" />
              {{ lang.label }}
              <v-icon v-if="selectedLang === lang.code" end size="12">
                mdi-check
              </v-icon>
            </v-btn>
          </div>
        </div>

        <v-divider />

        <!-- Theme -->
        <div class="px-5 py-4">
          <div class="d-flex align-center gap-2 mb-3">
            <v-icon size="16" color="primary">mdi-palette-outline</v-icon>
            <span class="section-label">{{ t('preferences.theme') }}</span>
          </div>
          <div class="d-flex gap-2">
            <v-card
              v-for="th in themes"
              :key="th.value"
              :variant="selectedTheme === th.value ? 'tonal' : 'outlined'"
              :color="selectedTheme === th.value ? 'primary' : undefined"
              rounded="lg"
              class="theme-card flex-grow-1 cursor-pointer"
              @click="changeTheme(th.value)"
            >
              <v-card-text class="pa-3 text-center">
                <v-icon :icon="th.icon" size="22" class="mb-1" />
                <div class="text-caption font-weight-medium">
                  {{ th.label }}
                </div>
              </v-card-text>
            </v-card>
          </div>
        </div>

        <v-divider />

        <!-- Density -->
        <div class="px-5 py-4">
          <div class="d-flex align-center gap-2 mb-3">
            <v-icon size="16" color="primary">mdi-view-compact-outline</v-icon>
            <span class="section-label">{{ t('preferences.density') }}</span>
          </div>
          <v-btn-toggle
            v-model="selectedDensity"
            rounded="lg"
            color="primary"
            variant="outlined"
            divided
            density="compact"
            class="w-100"
            mandatory
            @update:model-value="changeDensity"
          >
            <v-btn
              v-for="d in densities"
              :key="d.value"
              :value="d.value"
              size="small"
              class="flex-grow-1 text-none"
            >
              <v-icon start :icon="d.icon" size="14" />
              {{ d.label }}
            </v-btn>
          </v-btn-toggle>
        </div>
  </AppDialog>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useTheme, useLocale } from 'vuetify'
  import { usePreferencesStore } from '@/stores/preferencesStore'
  import AppDialog from '@/components/common/AppDialog.vue'

  const { current: vuetifyLocale } = useLocale()
  const model = defineModel()

  const { t, locale } = useI18n()
  const theme = useTheme()
  const prefsStore = usePreferencesStore()

  // Language
  const languages = computed(() => [
    {
      code: 'km',
      label: t('lang.km') || 'ខ្មែរ',
      imgSrc: 'https://flagcdn.com/w80/kh.png',
      alt: 'Khmer Flag'
    },
    {
      code: 'en',
      label: t('lang.en') || 'English',
      imgSrc: 'https://flagcdn.com/w80/gb.png',
      alt: 'English Flag'
    }
  ])
  const selectedLang = ref(locale.value || 'km')

  const changeLang = code => {
    locale.value = code
    vuetifyLocale.value = code
    selectedLang.value = code
    localStorage.setItem('lang', code)
  }

  // Theme
  const themes = computed(() => [
    {
      value: 'light',
      label: t('preferences.light'),
      icon: 'mdi-white-balance-sunny'
    },
    {
      value: 'dark',
      label: t('preferences.dark'),
      icon: 'mdi-moon-waning-crescent'
    },
    {
      value: 'auto',
      label: t('preferences.auto'),
      icon: 'mdi-theme-light-dark'
    }
  ])

  const selectedTheme = ref(localStorage.getItem('theme') || 'light')

  const applyTheme = val => {
    if (val === 'auto') {
      const hour = new Date().getHours()
      theme.change(hour >= 18 || hour < 6 ? 'dark' : 'light')
    } else {
      theme.change(val)
    }
  }

  const changeTheme = val => {
    selectedTheme.value = val
    localStorage.setItem('theme', val)
    applyTheme(val)
  }

  // Density
  const selectedDensity = ref(localStorage.getItem('density') || 'default')

  const densities = computed(() => [
    {
      value: 'default',
      label: t('preferences.comfortable'),
      icon: 'mdi-view-agenda-outline'
    },
    {
      value: 'compact',
      label: t('preferences.compact'),
      icon: 'mdi-view-list-outline'
    }
  ])

  const changeDensity = val => {
    selectedDensity.value = val
    prefsStore.setDensity(val)
  }

  onMounted(() => {
    const savedTheme = localStorage.getItem('theme') || 'light'
    selectedTheme.value = savedTheme
    applyTheme(savedTheme)
    prefsStore.setDensity(selectedDensity.value)
  })
</script>

<style scoped>
  .w-100 {
    width: 100%;
  }

  .section-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: rgba(var(--v-theme-on-surface), 0.55);
  }

  .lang-btn {
    min-width: 110px;
  }
  .flag-img {
    width: 20px;
    height: 14px;
    object-fit: cover;
    border-radius: 2px;
    flex-shrink: 0;
  }

  .theme-card {
    transition: transform 0.15s;
  }
  .theme-card:hover {
    transform: translateY(-2px);
  }
  .cursor-pointer {
    cursor: pointer;
  }
</style>
