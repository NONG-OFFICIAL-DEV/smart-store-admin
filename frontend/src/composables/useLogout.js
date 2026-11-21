import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/authStore'
import { useBranchStore } from '@/stores/branchStore'
import { usePosStore } from '@/stores/posStore'
import { useAppUtils } from '@nong-official-dev/core'

// Single source of truth for logging out — used by AppBarActions.vue (user
// menu) and SidebarFooter.vue (Sidebar-only/POS-focus layouts) so neither
// hand-rolls its own token/store cleanup.
export function useLogout() {
  const router = useRouter()
  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()
  const authStore = useAuthStore()
  const branchStore = useBranchStore()
  const posStore = usePosStore()

  async function performLogout() {
    try {
      // authStore.logout() already swallows its own API error internally —
      // this try/finally is belt-and-suspenders so a future change there
      // can never block the local logout below.
      await authStore.logout()
    } finally {
      localStorage.removeItem('token')
      // Clear before $reset() so its state() factory doesn't immediately
      // re-read the old value back in — otherwise the next person to log
      // in on this device would inherit this user's active branch.
      localStorage.removeItem('active_branch_id')
      authStore.$reset()
      branchStore.$reset()
      posStore.$reset()

      await router.replace({ name: 'Login' })

      notif(t('messages.logout_success'), { type: 'success', color: 'primary' })
    }
  }

  function logout() {
    const hasUnsavedOrder = posStore.items.length > 0
    if (!hasUnsavedOrder) {
      performLogout().catch(() => notif(t('messages.logout_failed'), { type: 'error', color: 'error' }))
      return
    }

    confirm({
      title: t('logout.confirm_title'),
      message: t('logout.confirm_message'),
      options: { type: 'warning' },
      agree: () => performLogout().catch(() => notif(t('messages.logout_failed'), { type: 'error', color: 'error' }))
    })
  }

  return { logout }
}
