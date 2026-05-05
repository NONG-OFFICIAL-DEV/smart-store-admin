// composables/useBusinessTypes.js
import { computed } from 'vue'
import { useTenantStore } from '@/stores/tenantStore'
import { resolveBuType } from '@/constants/businessTypes'

const FALLBACK = {
  icon: 'mdi-store-outline',
  color: 'grey',
  label: '—',
  code: ''
}

export function useBusinessTypes() {
  const store = useTenantStore()

  // v-select compatible list — built from DB rows, display info from registry
  const businessTypeOptions = computed(() =>
    store.businessTypes.map(bt => {
      const meta = resolveBuType(bt.code)
      return {
        value: bt.id,
        code: bt.code,
        label: bt.name,
        icon: meta.icon,
        color: meta.color
      }
    })
  )

  const resolveById = id => {
    const match = businessTypeOptions.value.find(o => o.value === id)
    return match ?? { ...FALLBACK, label: id ?? '—' }
  }

  const resolveByCode = code => {
    const match = businessTypeOptions.value.find(o => o.code === code)
    if (match) return match
    const meta = resolveBuType(code)
    return { ...FALLBACK, ...meta, label: code ?? '—', code: code ?? '' }
  }

  const resolve = value => {
    if (!value) return { ...FALLBACK }
    const isUuid =
      /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(
        value
      )
    return isUuid ? resolveById(value) : resolveByCode(value)
  }

  return { businessTypeOptions, resolveById, resolveByCode, resolve }
}
