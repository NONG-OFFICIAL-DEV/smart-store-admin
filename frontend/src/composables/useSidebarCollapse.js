import { computed } from 'vue'
import { useDisplay } from 'vuetify'
import { useUiPreferencesStore } from '@/stores/uiPreferencesStore'

// Before the user has ever touched the toggle, each permanent layout starts
// with its own sensible default — Icon-rail collapsed, Classic/Sidebar-only
// expanded. Once they toggle it once (any layout), that single choice is
// remembered and used everywhere (see uiPreferencesStore.sidebarCollapsed).
const DEFAULT_COLLAPSED_BY_VARIANT = {
  classic: false,
  sidebar_only: false,
  icon_rail: true
}

// Single source of truth for "is the sidebar collapsed right now," shared by
// Sidebar.vue (which renders it) and Layout.vue (which positions the
// floating collapse handle) so they can never disagree.
//
// variantRef must be a ref/computed holding the current layout variant
// ('classic' | 'sidebar_only' | 'icon_rail' | 'pos_focus').
export function useSidebarCollapse(variantRef) {
  const { mdAndDown } = useDisplay()
  const uiPrefs = useUiPreferencesStore()

  // POS-focus's drawer is always a temporary overlay; every other layout
  // becomes one too below the lg breakpoint (opened via a hamburger/fab
  // instead of showing the floating collapse handle).
  const isTemporary = computed(() => variantRef.value === 'pos_focus' || mdAndDown.value)

  const isCollapsed = computed(() => {
    if (isTemporary.value) return false
    if (uiPrefs.sidebarCollapsed !== null) return uiPrefs.sidebarCollapsed
    return DEFAULT_COLLAPSED_BY_VARIANT[variantRef.value] ?? false
  })

  function toggleCollapse() {
    uiPrefs.setSidebarCollapsed(!isCollapsed.value)
  }

  return { isTemporary, isCollapsed, toggleCollapse, mdAndDown }
}
