<template>
  <v-menu
    v-model="open"
    location="bottom end"
    transition="scale-transition"
    offset="8"
    :max-width="MENU_WIDTH"
  >
    <template #activator="{ props: menuProps }">
      <v-btn
        v-bind="menuProps"
        variant="text"
        rounded="lg"
        height="38"
        :width="BUTTON_WIDTH"
        class="lang-switch-btn px-2"
        :aria-label="t('lang.change')"
      >
        <div class="lang-btn-inner">
          <v-img
            :src="currentLanguage.flag"
            width="21"
            height="21"
            class="flag-img"
            cover
          />
          <span class="lang-label">{{ currentLanguage.label }}</span>
          <!-- <v-icon
            :icon="open ? 'mdi-chevron-up' : 'mdi-chevron-down'"
            size="16"
            class="lang-chevron"
          /> -->
        </div>
      </v-btn>
    </template>

    <v-list class="pa-1" rounded="lg" density="compact">
      <v-list-item
        v-for="opt in options"
        :key="opt.code"
        :active="locale === opt.code"
        rounded="md"
        @click="select(opt.code)"
      >
        <template #prepend>
          <v-img
            :src="opt.flag"
            width="22"
            height="22"
            class="flag-img me-3"
            cover
          />
        </template>

        <v-list-item-title class="lang-option-title">
          {{ opt.label }}
        </v-list-item-title>
      </v-list-item>
    </v-list>
  </v-menu>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import authService from '@/api/auth'

// Fixed sizes so English and Khmer look the same
const BUTTON_WIDTH = 132
const MENU_WIDTH = 100

const { t, locale } = useI18n()
const open = ref(false)

const options = computed(() => [
  { code: 'en', label: t('lang.en'), flag: '/images/gb.png' },
  { code: 'km', label: t('lang.km'), flag: '/images/kh.png' }
])

const currentLanguage = computed(
  () => options.value.find(o => o.code === locale.value) ?? options.value[0]
)

function select(code) {
  open.value = false
  if (locale.value === code) return

  locale.value = code
  localStorage.setItem('lang', code)
  document.documentElement.setAttribute('lang', code)

  authService
    .updatePreferences({ preferred_language: code })
    .catch(() => {})
}
</script>

<style scoped>
.lang-switch-btn {
  min-width: 0;
  letter-spacing: 0;
  text-transform: none;
}

.lang-switch-btn:hover {
  background: rgba(var(--v-theme-on-surface), 0.05);
}

/* Fixed layout inside the button: flag | label (fills) | chevron */
.lang-btn-inner {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 8px;
}

.lang-label {
  flex: 1;
  min-width: 0;
  text-align: start;
  font-size: 13px;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.flag-img {
  flex: none;
  border-radius: 4px;
  overflow: hidden;
}

.lang-chevron {
  flex: none;
  opacity: 0.65;
}

.lang-option {
  min-height: 42px;
}

.lang-option-title {
  font-size: 13px;
  font-weight: 500;
}
</style>