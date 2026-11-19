import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'
import { createVuetify } from 'vuetify'
import { ref, watch } from 'vue'
import { km, en } from 'vuetify/locale'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import * as labs from 'vuetify/labs/components'
import { VDateInput } from 'vuetify/labs/VDateInput'
import { VColorInput } from 'vuetify/labs/VColorInput'
import { KhmerDateAdapter } from './khmerDateAdapter'

// Vuetify's own density scale is 'default' (tallest) > 'comfortable' > 'compact'
// (shortest). This app only ever offers two states in PreferencesDialog —
// "Comfortable" and "Compact" — so any stored/legacy value of 'default'
// (Vuetify's tallest, unused level) is normalized to 'comfortable' here.
// Without this, a stored 'default' renders noticeably larger than the
// 'comfortable' look every field was originally designed around.
export function normalizeDensity(value) {
  return value === 'default' || !value ? 'comfortable' : value
}

export const appDensity = ref(normalizeDensity(localStorage.getItem('density')))
const savedLocale = localStorage.getItem('lang') || 'km'

// ── Accent colors ─────────────────────────────────────────────────────────
// Five brand-consistent options offered in the Appearance page — 'teal' is
// the original/default hue this app shipped with (unchanged values).
export const ACCENTS = {
  teal: { light: '#00838F', dark: '#26C6DA' },
  blue: { light: '#1565C0', dark: '#42A5F5' },
  orange: { light: '#E65100', dark: '#FFA726' },
  purple: { light: '#6A1B9A', dark: '#BA68C8' },
  rose: { light: '#AD1457', dark: '#F06292' }
}

export function normalizeAccent(value) {
  return ACCENTS[value] ? value : 'teal'
}

const savedAccent = normalizeAccent(localStorage.getItem('accent'))

// ── Theme mode (light/dark/auto) ──────────────────────────────────────────
// Resolved synchronously, at module load — BEFORE createVuetify() ever runs
// — same convention already used for savedLocale above. Previously nothing
// read the stored mode until a dialog's onMounted() fired well after first
// paint, so every load flashed Vuetify's hardcoded 'light' default first.
function resolveInitialThemeName(mode) {
  if (mode === 'dark') return 'dark'
  if (mode === 'auto') {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
  }
  return 'light'
}

const savedThemeMode = localStorage.getItem('theme') || 'light'
const initialThemeName = resolveInitialThemeName(savedThemeMode)

const DENSITY_FIELDS = [
  'VTextField',
  'VSelect',
  'VTextarea',
  'VAutocomplete',
  'VCombobox',
  'VDateInput',
  'VDataTable',
  'VDataTableServer',
  'VTable'
]

const vuetify = createVuetify({
  display: { mobileBreakpoint: 'sm' },
  components: { VDateInput, VColorInput, ...components, ...labs },
  directives,
  defaults: {
    VDateInput: {
      density: 'comfortable',
      variant: 'outlined',
      color: 'primary',
      prependIcon: '',
      appendInnerIcon: '$calendar',
      inputFormat: 'DD-MM-YYYY',
      hideActions: true
    },
    VSelect: { density: 'comfortable', variant: 'outlined', color: 'primary' },
    VTextField: {
      variant: 'outlined',
      density: 'comfortable',
      color: 'primary',
      rounded: 'lg'
    },
    VTextarea: {
      variant: 'outlined',
      density: 'comfortable',
      color: 'primary',
      autoGrow: true,
      rows: 3
    },
    VAutocomplete: {
      variant: 'outlined',
      density: 'comfortable',
      color: 'primary'
    },
    VCombobox: {
      variant: 'outlined',
      density: 'comfortable',
      color: 'primary'
    },
    VDataTable: { class: 'rounded-lg' },
    VDataTableServer: { class: 'rounded-lg' },
    // Calm, flat surface as the app-wide default — most screens already
    // hand-wrote `rounded="lg" border elevation="0"` individually; this
    // makes that the baseline so new/unstyled cards match without it, and
    // any card that deliberately wants a shadow/different radius (menus,
    // popovers) still overrides these per-instance as before.
    VCard: { rounded: 'lg', border: true, elevation: 0 }
  },
  theme: {
    defaultTheme: initialThemeName,
    themes: {
      light: {
        dark: false,
        colors: {
          primary: ACCENTS[savedAccent].light,
          secondary: '#c17290',
          textField: '#2f9dab',
          icon: '#653748',
          btnEdit: '#a0627f',
          gray: '#f2f2f2',
          warning: '#FB8C00',
          error: '#B00020'
        }
      },
      // Same brand hues as `light`, lightened/desaturated for contrast on a
      // dark surface — previously missing entirely, so PreferencesDialog's
      // Dark/Auto option silently fell back to Vuetify's generic default
      // dark palette instead of this app's own brand. `background`/`surface`
      // and friends are left unset on purpose — Vuetify's own dark defaults
      // for those are already sensible and shouldn't be second-guessed here.
      dark: {
        dark: true,
        colors: {
          primary: ACCENTS[savedAccent].dark,
          secondary: '#D690AC',
          textField: '#4DD0E1',
          icon: '#D8A8BC',
          btnEdit: '#D8A8BC',
          gray: '#2A2A2A',
          warning: '#FFB74D',
          error: '#EF5350'
        }
      }
    }
  },
  locale: {
    messages: { km, en },
    locale: savedLocale,
    fallback: 'en'
  },
  icons: { iconfont: 'mdi' },
  date: {
    adapter: KhmerDateAdapter,
    locale: {
      en: 'en-GB',
      km: 'km-KH'
    }
  }
})

watch(
  appDensity,
  val => {
    DENSITY_FIELDS.forEach(key => {
      vuetify.defaults.value[key] = {
        ...vuetify.defaults.value[key],
        density: val
      }
    })
  },
  { immediate: true }
)

// Runtime accent swap — called from PreferencesDialog's live preview/save.
// Mutates both theme definitions directly (not just the currently-active
// one) so switching light<->dark/auto afterward keeps the chosen accent
// instead of reverting to whichever hex was baked in at module load.
export function applyAccent(key) {
  const accent = normalizeAccent(key)
  vuetify.theme.themes.value.light.colors.primary = ACCENTS[accent].light
  vuetify.theme.themes.value.dark.colors.primary = ACCENTS[accent].dark
}

export default vuetify
