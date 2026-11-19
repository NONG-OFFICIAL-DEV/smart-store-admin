import { defineStore } from 'pinia'
import authService from '@/api/auth'
import vuetify, { appDensity, normalizeDensity, applyAccent, normalizeAccent } from '@/plugins/vuetify'

const STORAGE_KEY = 'ui_preferences'
const LAYOUTS = ['classic', 'sidebar_only', 'icon_rail', 'pos_focus']

function normalizeLayout(value) {
  return LAYOUTS.includes(value) ? value : 'classic'
}

// Same module-load-time-restore convention as plugins/vuetify.js — the
// legacy standalone 'theme'/'density'/'accent' keys are the fallback for a
// browser that saved preferences before this bag existed; both are kept
// written together (see persistLocally) so neither path ever goes stale.
function loadCached() {
  let parsed = {}
  try {
    parsed = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}')
  } catch {
    parsed = {}
  }
  return {
    layout: normalizeLayout(parsed.layout),
    theme: parsed.theme || localStorage.getItem('theme') || 'light',
    accent: normalizeAccent(parsed.accent || localStorage.getItem('accent')),
    density: normalizeDensity(parsed.density || localStorage.getItem('density'))
  }
}

function applyThemeMode(mode) {
  if (mode === 'auto') {
    vuetify.theme.change(window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
  } else {
    vuetify.theme.change(mode)
  }
}

export const useUiPreferencesStore = defineStore('uiPreferences', {
  state: () => loadCached(),

  actions: {
    persistLocally() {
      localStorage.setItem(
        STORAGE_KEY,
        JSON.stringify({ layout: this.layout, theme: this.theme, accent: this.accent, density: this.density })
      )
      // Legacy standalone keys — PreferencesDialog/AuthLayout/App.vue's
      // Ctrl+Shift+L shortcut still read 'theme'/'density' directly today
      // (Phase 3 consolidates everything onto this store).
      localStorage.setItem('theme', this.theme)
      localStorage.setItem('density', this.density)
      localStorage.setItem('accent', this.accent)
    },

    // Re-applies everything currently held — used once after login (see
    // hydrateFromServer) when the server's copy actually differs from what
    // was already painted from the synchronous localStorage restore in
    // plugins/vuetify.js.
    applyAll() {
      applyThemeMode(this.theme)
      applyAccent(this.accent)
      appDensity.value = this.density
    },

    setLayout(value) {
      this.layout = normalizeLayout(value)
      this.persistAndSync()
    },
    setTheme(value) {
      this.theme = value
      applyThemeMode(value)
      this.persistAndSync()
    },
    setAccent(value) {
      this.accent = normalizeAccent(value)
      applyAccent(this.accent)
      this.persistAndSync()
    },
    setDensity(value) {
      this.density = normalizeDensity(value)
      appDensity.value = this.density
      this.persistAndSync()
    },

    // Fire-and-forget — a failed save just means the pick stays
    // local-only until the next successful change; it never blocks the UI
    // from reflecting the choice immediately.
    persistAndSync() {
      this.persistLocally()
      authService
        .updatePreferences({
          ui_preferences: { layout: this.layout, theme: this.theme, accent: this.accent, density: this.density }
        })
        .catch(() => {})
    },

    // Called once per authStore.fetchMe() resolution — reconciles the
    // authoritative server copy against whatever the localStorage cache
    // already applied synchronously at boot. A real mismatch only happens
    // after changing preferences on a different device/browser.
    hydrateFromServer(serverPrefs) {
      if (!serverPrefs) return

      const next = {
        layout: normalizeLayout(serverPrefs.layout ?? this.layout),
        theme: serverPrefs.theme ?? this.theme,
        accent: normalizeAccent(serverPrefs.accent ?? this.accent),
        density: normalizeDensity(serverPrefs.density ?? this.density)
      }
      const current = { layout: this.layout, theme: this.theme, accent: this.accent, density: this.density }
      const changed = JSON.stringify(next) !== JSON.stringify(current)

      Object.assign(this, next)
      this.persistLocally()
      if (changed) this.applyAll()
    }
  }
})
