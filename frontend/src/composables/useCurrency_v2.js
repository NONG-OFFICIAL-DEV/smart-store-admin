import { useAuthStore } from '@/stores/authStore'
import { computed } from 'vue'
import { formatCurrency, formatKHR } from '@nong-official-dev/core'

// Single shared money helper. Every stored amount (order/payment totals,
// table "due" figures, etc.) is already in the TENANT'S OWN base currency —
// USD for most tenants, but a tenant can be set to KHR (Settings > Profile,
// `tenant.currency`), in which case every amount in the database for that
// tenant is a Riel figure, not a dollar one. formatBase() is the one place
// that decides how to print a raw stored amount — it must never assume USD
// (that was the bug: a KHR-based tenant's totals got a "$" slapped on the
// raw Riel number, e.g. "$40,003.50" for what was actually ៛40,003.50).
// Only the tenant's own currency is ever shown — no secondary/converted
// currency line.
export function useCurrency() {
  const authStore = useAuthStore()

  const currency = computed(() => authStore.currency ?? 'USD')

  // `amount` is already expressed in the tenant's own base currency —
  // format it with the matching symbol/style, never a hardcoded one.
  function formatBase(amount) {
    return currency.value === 'KHR' ? formatKHR(amount) : formatCurrency(amount)
  }

  // Legacy single-currency formatter — identical to formatBase, kept as an
  // alias for components that haven't moved to the explicit formatBase name
  // yet.
  function format(value) {
    return formatBase(value)
  }

  function currencySymbol() {
    return currency.value === 'KHR' ? '៛' : '$'
  }

  return { format, formatBase, currency, currencySymbol }
}
