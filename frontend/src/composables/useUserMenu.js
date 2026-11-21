import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'
import { useBranchStore } from '@/stores/branchStore'

// Shared "jump to My account" + "open customer display" logic — used by
// AppBarActions.vue (app bar) and SidebarFooter.vue (sidebar card). The
// avatar in both places now navigates straight to the account page's
// Profile tab instead of opening a dropdown menu: Preferences/Keyboard are
// already reachable as tabs on that same page once there, so a separate
// menu listing them again was pure duplication.
export function useUserMenu() {
  const router = useRouter()
  const authStore = useAuthStore()
  const branchStore = useBranchStore()

  // Enabled per-branch (Settings > Customer Display), not tenant-wide — see
  // ShopBranchSwitcher.vue, which fetches branchStore.branches (incl.
  // customer_display_settings) for every role, staff included.
  const customerDisplayEnabled = computed(
    () =>
      branchStore.branches.find(b => b.id === authStore.activeBranchId)
        ?.customer_display_settings?.enabled === true
  )

  function goToAccount(tab) {
    router.push({ name: 'account', query: { tab } })
  }

  function openCustomerDisplay() {
    window.open('/customer-display', '_blank', 'noopener')
  }

  return { goToAccount, customerDisplayEnabled, openCustomerDisplay }
}
