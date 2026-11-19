
<template>
  <v-menu
    location="bottom end"
    transition="scale-transition"
    offset="8"
    min-width="190"
  >
    <template #activator="{ props: menuProps }">
      <v-btn
        v-bind="menuProps"
        variant="text"
        rounded="lg"
        size="default"
        class="lang-switch-btn px-2"
        height="38"
      >
        <v-img
          :src="currentFlag"
          width="21"
          height="21"
          class="flag-img me-2"
          cover
        />

        <span class="lang-label">
          {{ currentLanguage?.label }}
        </span>

        <v-icon
          icon="mdi-chevron-down"
          size="16"
          class="ms-1 lang-chevron"
        />
      </v-btn>
    </template>

    <v-list
      class="lang-menu pa-1"
      rounded="lg"
      density="compact"
    >
      <v-list-item
        v-for="opt in options"
        :key="opt.code"
        :active="locale === opt.code"
        :value="opt.code"
        rounded="md"
        class="lang-option"
        @click="select(opt.code)"
      >
        <template #prepend>
          <v-img
            :src="opt.flag"
            width="22"
            height="22"
            class="flag-img"
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
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import authService from '@/api/auth'

const { t, locale } = useI18n()

const options = computed(() => [
  { code: 'en', label: t('lang.en'), flag: '/images/gb.png' },
  { code: 'km', label: t('lang.km'), flag: '/images/kh.png' }
])

const currentLanguage = computed(
  () => options.value.find(o => o.code === locale.value) ?? options.value[0]
)

const currentFlag = computed(() => currentLanguage.value.flag)

function select(code) {
  if (locale.value === code) return

  locale.value = code
  localStorage.setItem('lang', code)

  authService
    .updatePreferences({ preferred_language: code })
    .catch(() => {})
}
</script>

<style scoped>
/* .lang-switch-btn {
  min-width: 0;
  font-weight: 500;
  letter-spacing: 0;
  border: 1px solid rgba(var(--v-border-color), 0.12);
  background: rgba(var(--v-theme-surface), 0.5);
} */

.lang-switch-btn:hover {
  background: rgba(var(--v-theme-on-surface), 0.05);
}

.flag-img {
  border-radius: 4px;
  flex-shrink: 0;
  overflow: hidden;
}

.lang-label {
  font-size: 13px;
  font-weight: 600;
}

.lang-chevron {
  opacity: 0.65;
}

.lang-menu {
  border: 1px solid rgba(var(--v-border-color), 0.12);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}

.lang-option {
  min-height: 42px;
}

.lang-option-title {
  font-size: 13px;
  font-weight: 500;
}
</style>
