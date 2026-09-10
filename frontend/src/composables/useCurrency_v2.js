import { useAuthStore } from '@/stores/authStore'
import { computed } from 'vue'
import { formatCurrency, formatKHR } from '@nong-official-dev/core'

export function useCurrency() {
  const authStore = useAuthStore()

  const currency = computed(() => authStore.currency ?? 'USD')

  function format(value) {
    if (currency.value === 'KHR') return formatKHR(value)
    return formatCurrency(value)
  }

  function currencySymbol() {
    return currency.value === 'KHR' ? '៛' : '$'
  }

  return { format, currency, currencySymbol }
}
