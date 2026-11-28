import { computed } from 'vue'
import { useAuthStore } from '@/stores/authStore'
import { usePermission } from '@/composables/usePermission'

// Shared by CashRegisterWidget.vue (its own trigger's v-if) and any parent
// that wraps it in extra layout markup (e.g. SidebarFooter.vue's bordered
// row) — both need to agree on whether the widget will render anything at
// all, otherwise a parent wrapper can end up showing an empty bordered row
// with nothing inside it when the feature is hidden/disabled.
export function useCashRegisterAccess() {
  const authStore = useAuthStore()
  const { canAny } = usePermission()

  const hasCashRegisterAccess = computed(() =>
    !!(authStore.staff_id || authStore.isOwner) &&
    canAny('payments.manage', 'cash_drawers.manage') &&
    authStore.posSettings?.cash_register_enabled !== false
  )

  return { hasCashRegisterAccess }
}
