import { useRouter } from 'vue-router'

// Shared "jump to My account" logic — used by AppBarActions.vue (app bar)
// and SidebarFooter.vue (sidebar card). The avatar in both places now
// navigates straight to the account page's Profile tab instead of opening a
// dropdown menu: Preferences/Keyboard are already reachable as tabs on that
// same page once there, so a separate menu listing them again was pure
// duplication.
export function useUserMenu() {
  const router = useRouter()

  function goToAccount(tab) {
    router.push({ name: 'account', query: { tab } })
  }

  return { goToAccount }
}
