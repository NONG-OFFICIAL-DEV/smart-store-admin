import { useAuthStore } from '@/stores/authStore'
import { computed } from 'vue'
import {
  formatCurrency,
  formatKHR,
  formatCurrencyNoSymbol
} from '@nong-official-dev/core'

export function useCurrency() {
  const authStore = useAuthStore()

  const locale = computed(() => authStore.tenant?.locale ?? 'en-US')
  const currency = computed(() => authStore.currency ?? 'USD')

  function format(value) {
    if (currency.value === 'KHR') return formatKHR(value)
    return formatCurrency(value)
  }

  function currencySymbol() {
    return currency.value === 'KHR' ? '៛' : '$'
  }

  function formatNoSymbol(value) {
    return formatCurrencyNoSymbol(value, locale.value)
  }

  return { format, formatNoSymbol, currency, locale, currencySymbol }
}
