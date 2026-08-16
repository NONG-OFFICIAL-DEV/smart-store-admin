// composables/useSidebarMenu.js
//
// ─── Reusable sidebar menu filtering + expand/collapse state ──────────────────
//
// Takes a plain data config (see config/sidebarMenu.js) and turns it into a
// permission-filtered, i18n-resolved tree the UI can render generically.
// Kept separate from Sidebar.vue so the visibility rules are testable and
// reusable (e.g. for a mobile nav) without touching Vuetify markup.

import { ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/authStore'

const STORAGE_KEY = 'sidebar-open-groups'

export function useSidebarMenu(rawMenu) {
  const authStore = useAuthStore()
  const route = useRoute()
  const { t } = useI18n()

  // ── Context available to every node's permission()/visible() rule ────────
  const ctx = computed(() => ({
    isSuperAdmin: authStore.isSuperAdmin,
    isOwner: authStore.isOwner,
    isFood: authStore.isFood,
    isMart: authStore.isMart,
    hasPlan: level => authStore.hasPlan(level),
    hasFeature: code => authStore.hasFeature(code),
    can: code => authStore.can(code),
    canAny: (...codes) => authStore.canAny(...codes)
  }))

  // A node is visible when both its custom `visible()` rule (if any) AND its
  // `permission` check (if any) pass — either alone, or combined.
  function isVisible(node) {
    const c = ctx.value
    if (node.visible && !node.visible(c)) return false
    if (node.permission) {
      const codes = Array.isArray(node.permission)
        ? node.permission
        : [node.permission]
      if (!c.canAny(...codes)) return false
    }
    return true
  }

  function resolveNode(node) {
    const resolved = {
      key: node.key,
      title: t(node.titleKey),
      icon: node.icon,
      path: node.path,
      newTab: node.newTab,
      badge: typeof node.badge === 'function' ? node.badge(ctx.value) : node.badge
    }
    if (node.children) {
      resolved.children = node.children.filter(isVisible).map(resolveNode)
    }
    return resolved
  }

  // Filters recursively and drops any group left with zero visible children —
  // "automatically hide empty categories" from the requirements.
  const filteredMenu = computed(() =>
    rawMenu
      .filter(isVisible)
      .map(resolveNode)
      .filter(item => !item.children || item.children.length > 0)
  )

  // ── Active-route highlighting ──────────────────────────────────────────
  function isGroupActive(group) {
    if (!group.children) return false
    return group.children.some(c => c.path && route.path.startsWith(c.path))
  }

  // ── Expand/collapse state — persisted, defaults to the active group ────
  function loadStoredOpen() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY)
      if (raw) return JSON.parse(raw)
    } catch {
      // malformed storage — fall back to computed default
    }
    return null
  }

  function defaultOpenGroups() {
    const active = filteredMenu.value.find(isGroupActive)
    return active ? [active.key] : []
  }

  const openGroups = ref(loadStoredOpen() ?? defaultOpenGroups())

  watch(
    openGroups,
    val => localStorage.setItem(STORAGE_KEY, JSON.stringify(val)),
    { deep: true }
  )

  return { filteredMenu, openGroups, isGroupActive }
}
