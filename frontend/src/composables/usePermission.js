// import { computed } from 'vue'
// import { useAuthStore } from '@/stores/authStore'

// export function usePermission() {
//   const authStore = useAuthStore()

//   const can = p => authStore.permissions.includes(p)
//   const canAny = (...ps) => ps.some(p => can(p))
//   const canAll = (...ps) => ps.every(p => can(p))

//   return { can, canAny, canAll }
// }


import { useAuthStore } from '@/stores/authStore'

export function usePermission() {
  const auth = useAuthStore()

  // These come directly from the store
  // can/canAny/canAll already handle super_admin + owner bypass
  const can    = (code)      => auth.can(code)
  const canAny = (...codes)  => auth.canAny(...codes)
  const canAll = (...codes)  => auth.canAll(...codes)

  // Role helpers — use these for UI sections, not permission buttons
  const isSuperAdmin  = () => auth.isSuperAdmin
  const isOwner       = () => auth.isOwner
  const isRegularStaff= () => !auth.isSuperAdmin && !auth.isOwner

  return {
    can,
    canAny,
    canAll,
    isSuperAdmin,
    isOwner,
    isRegularStaff,
  }
}