import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import authService from '@/api/auth'

// Single source of truth for language switching — vue-i18n locale,
// localStorage 'lang', and persisting the pick via updatePreferences().
// Used by LanguageSwitch.vue (app bar) and SidebarFooter.vue (sidebar card)
// so neither hand-rolls its own copy of this logic.
export function useLanguage() {
  const { t, locale } = useI18n()

  const options = computed(() => [
    { code: 'en', label: t('lang.en'), flag: '/images/gb.png' },
    { code: 'km', label: t('lang.km'), flag: '/images/kh.png' }
  ])

  const currentLanguage = computed(
    () => options.value.find(o => o.code === locale.value) ?? options.value[0]
  )

  function select(code) {
    if (locale.value === code) return

    locale.value = code
    localStorage.setItem('lang', code)
    document.documentElement.setAttribute('lang', code)

    authService.updatePreferences({ preferred_language: code }).catch(() => {})
  }

  return { options, currentLanguage, locale, select }
}
