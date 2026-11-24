<template>
  <v-row>
    <!-- ── Options ──────────────────────────────────────────────────────── -->
    <v-col cols="12" md="7">
      <!-- Layout -->
      <div class="px-0 py-4">
        <div class="d-flex align-center gap-2 mb-3">
          <v-icon size="16" color="primary">mdi-view-dashboard-outline</v-icon>
          <span class="section-label">{{ t('preferences.layout') }}</span>
        </div>
        <v-row density="compact">
          <v-col v-for="opt in layoutOptions" :key="opt.value" cols="6">
            <v-card
              :variant="prefs.layout === opt.value ? 'tonal' : 'outlined'"
              :color="prefs.layout === opt.value ? 'primary' : undefined"
              rounded="lg"
              class="layout-card cursor-pointer"
              @click="prefs.setLayout(opt.value)"
            >
              <v-card-text class="pa-3">
                <div class="layout-thumb" :class="`layout-thumb--${opt.value}`">
                  <div v-if="opt.value === 'classic' || opt.value === 'icon_rail'" class="thumb-bar" />
                  <div class="thumb-body">
                    <div
                      v-if="opt.value !== 'pos_focus'"
                      class="thumb-rail"
                      :class="{ 'thumb-rail--wide': opt.value === 'sidebar_only' }"
                    />
                    <div v-else class="thumb-fab" />
                    <div class="thumb-content" />
                  </div>
                </div>
                <div class="text-body-medium font-weight-bold mt-2">{{ opt.label }}</div>
                <div class="text-body-small text-medium-emphasis">{{ opt.hint }}</div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </div>

      <v-divider />

      <!-- Mode -->
      <div class="px-0 py-4">
        <div class="d-flex align-center gap-2 mb-3">
          <v-icon size="16" color="primary">mdi-palette-outline</v-icon>
          <span class="section-label">{{ t('preferences.theme') }}</span>
        </div>
        <div class="d-flex gap-2">
          <v-card
            v-for="th in themes"
            :key="th.value"
            :variant="prefs.theme === th.value ? 'tonal' : 'outlined'"
            :color="prefs.theme === th.value ? 'primary' : undefined"
            rounded="lg"
            class="theme-card flex-grow-1 cursor-pointer"
            @click="prefs.setTheme(th.value)"
          >
            <v-card-text class="pa-3 text-center">
              <v-icon :icon="th.icon" size="22" class="mb-1" />
              <div class="text-body-small font-weight-medium">{{ th.label }}</div>
            </v-card-text>
          </v-card>
        </div>
      </div>

      <v-divider />

      <!-- Accent -->
      <div class="px-0 py-4">
        <div class="d-flex align-center gap-2 mb-3">
          <v-icon size="16" color="primary">mdi-palette-swatch-outline</v-icon>
          <span class="section-label">{{ t('preferences.accent') }}</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button
            v-for="key in accentKeys"
            :key="key"
            type="button"
            class="accent-swatch"
            :class="{ 'accent-swatch--selected': prefs.accent === key }"
            :style="{ background: isDark ? ACCENTS[key].dark : ACCENTS[key].light }"
            :aria-label="t(`preferences.accents.${key}`)"
            :aria-pressed="prefs.accent === key"
            @click="prefs.setAccent(key)"
          >
            <v-icon v-if="prefs.accent === key" icon="mdi-check" size="16" color="white" />
          </button>
        </div>
      </div>

      <v-divider />

      <!-- Density -->
      <div class="px-0 py-4">
        <div class="d-flex align-center gap-2 mb-3">
          <v-icon size="16" color="primary">mdi-view-compact-outline</v-icon>
          <span class="section-label">{{ t('preferences.density') }}</span>
        </div>
        <v-btn-toggle
          :model-value="prefs.density"
          rounded="lg"
          color="primary"
          variant="outlined"
          divided
          density="compact"
          class="w-100"
          mandatory
          @update:model-value="prefs.setDensity"
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
    </v-col>

    <!-- ── Live preview ─────────────────────────────────────────────────── -->
    <v-col cols="12" md="5">
      <div class="preview-label text-body-small text-medium-emphasis font-weight-medium mb-2">
        {{ t('preferences.preview') }}
      </div>
      <div class="preview-shell" :class="`preview-shell--${prefs.layout}`">
        <div v-if="prefs.layout === 'classic' || prefs.layout === 'icon_rail'" class="preview-topbar">
          <span class="preview-dot" />
          <span class="preview-pill" />
          <v-spacer />
          <span class="preview-avatar" />
        </div>
        <div class="preview-body">
          <div v-if="prefs.layout !== 'pos_focus'" class="preview-sidebar" :class="`preview-sidebar--${prefs.layout}`">
            <span v-if="prefs.layout === 'sidebar_only'" class="preview-dot mb-2" />
            <span class="preview-nav-item preview-nav-item--active" />
            <span class="preview-nav-item" />
            <span class="preview-nav-item" />
            <span v-if="prefs.layout === 'sidebar_only'" class="preview-nav-item mt-auto" />
          </div>
          <div v-else class="preview-fab" />
          <div class="preview-content" :class="{ 'preview-content--compact': prefs.density === 'compact' }">
            <div class="preview-card preview-card--accent" />
            <div class="preview-card" />
            <div class="preview-card" />
          </div>
        </div>
      </div>
    </v-col>
  </v-row>
</template>

<script setup>
  import { computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useTheme } from 'vuetify'
  import { useUiPreferencesStore } from '@/stores/uiPreferencesStore'
  import { ACCENTS } from '@/plugins/vuetify'

  const { t } = useI18n()
  const theme = useTheme()
  const prefs = useUiPreferencesStore()

  const isDark = computed(() => theme.global.current.value.dark)
  const accentKeys = Object.keys(ACCENTS)

  const layoutOptions = computed(() => [
    {
      value: 'classic',
      label: t('preferences.layouts.classic.label'),
      hint: t('preferences.layouts.classic.hint')
    },
    {
      value: 'sidebar_only',
      label: t('preferences.layouts.sidebar_only.label'),
      hint: t('preferences.layouts.sidebar_only.hint')
    },
    {
      value: 'icon_rail',
      label: t('preferences.layouts.icon_rail.label'),
      hint: t('preferences.layouts.icon_rail.hint')
    },
    {
      value: 'pos_focus',
      label: t('preferences.layouts.pos_focus.label'),
      hint: t('preferences.layouts.pos_focus.hint')
    }
  ])

  const themes = computed(() => [
    { value: 'light', label: t('preferences.light'), icon: 'mdi-white-balance-sunny' },
    { value: 'dark', label: t('preferences.dark'), icon: 'mdi-moon-waning-crescent' },
    { value: 'auto', label: t('preferences.auto'), icon: 'mdi-theme-light-dark' }
  ])

  const densities = computed(() => [
    { value: 'comfortable', label: t('preferences.comfortable'), icon: 'mdi-view-agenda-outline' },
    { value: 'compact', label: t('preferences.compact'), icon: 'mdi-view-list-outline' }
  ])
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

  .theme-card {
    transition: transform 0.15s;
  }
  .theme-card:hover {
    transform: translateY(-2px);
  }
  .cursor-pointer {
    cursor: pointer;
  }

  /* ── Accent swatches ───────────────────────────────────────────────────── */
  .accent-swatch {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    outline: 2px solid transparent;
    outline-offset: 2px;
    transition: outline-color 0.15s;
  }
  .accent-swatch--selected {
    outline-color: rgba(var(--v-theme-on-surface), 0.4);
  }

  /* ── Layout cards + wireframe thumbnails ───────────────────────────────── */
  .layout-card {
    transition: transform 0.15s;
  }
  .layout-card:hover {
    transform: translateY(-2px);
  }
  .layout-thumb {
    width: 100%;
    height: 56px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
    display: flex;
    flex-direction: column;
    background: rgba(var(--v-theme-on-surface), 0.03);
    position: relative;
  }
  .thumb-bar {
    height: 10px;
    background: rgba(var(--v-theme-primary), 0.5);
    flex-shrink: 0;
  }
  .thumb-body {
    flex: 1;
    display: flex;
    min-height: 0;
  }
  .thumb-rail {
    width: 14px;
    background: rgba(var(--v-theme-on-surface), 0.18);
    flex-shrink: 0;
  }
  .thumb-rail--wide {
    width: 28px;
  }
  .thumb-content {
    flex: 1;
    background: rgba(var(--v-theme-on-surface), 0.07);
    margin: 4px;
    border-radius: 3px;
  }
  .thumb-fab {
    position: absolute;
    top: 4px;
    left: 4px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: rgb(var(--v-theme-primary));
    z-index: 1;
  }

  /* ── Live preview panel ─────────────────────────────────────────────────── */
  .preview-shell {
    border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
    border-radius: 12px;
    overflow: hidden;
    height: 220px;
    display: flex;
    flex-direction: column;
    background: rgb(var(--v-theme-surface));
  }
  .preview-topbar {
    flex-shrink: 0;
    height: 24px;
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0 8px;
    border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  }
  .preview-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: rgb(var(--v-theme-primary));
    flex-shrink: 0;
  }
  .preview-pill {
    width: 36px;
    height: 6px;
    border-radius: 999px;
    background: rgba(var(--v-theme-on-surface), 0.15);
  }
  .preview-avatar {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: rgba(var(--v-theme-on-surface), 0.2);
    flex-shrink: 0;
  }
  .preview-body {
    flex: 1;
    display: flex;
    min-height: 0;
  }
  .preview-sidebar {
    width: 72px;
    flex-shrink: 0;
    border-right: 1px solid rgba(var(--v-theme-on-surface), 0.1);
    padding: 8px 6px;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .preview-sidebar--icon_rail {
    width: 28px;
  }
  .preview-nav-item {
    height: 7px;
    border-radius: 4px;
    background: rgba(var(--v-theme-on-surface), 0.1);
  }
  .preview-nav-item--active {
    background: rgb(var(--v-theme-primary));
  }
  .preview-fab {
    position: absolute;
    margin: 6px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: rgb(var(--v-theme-primary));
  }
  .preview-content {
    flex: 1;
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .preview-content--compact {
    padding: 6px;
    gap: 4px;
  }
  .preview-card {
    height: 32px;
    border-radius: 6px;
    background: rgba(var(--v-theme-on-surface), 0.06);
  }
  .preview-content--compact .preview-card {
    height: 22px;
  }
  .preview-card--accent {
    background: rgba(var(--v-theme-primary), 0.18);
    border: 1px solid rgba(var(--v-theme-primary), 0.35);
  }
</style>
