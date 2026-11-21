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
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useLanguage } from '@/composables/useLanguage'

// Fixed sizes so English and Khmer look the same
const BUTTON_WIDTH = 132
const MENU_WIDTH = 100

const { t } = useI18n()
const { options, currentLanguage, locale, select: selectLanguage } = useLanguage()
const open = ref(false)

function select(code) {
  open.value = false
  selectLanguage(code)
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