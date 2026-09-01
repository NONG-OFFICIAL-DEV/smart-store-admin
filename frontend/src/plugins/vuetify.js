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
    VDataTableServer: { class: 'rounded-lg' }
  },
  theme: {
    themes: {
      light: {
        dark: false,
        colors: {
          primary: '#00838F',
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
          primary: '#26C6DA',
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

export default vuetify
