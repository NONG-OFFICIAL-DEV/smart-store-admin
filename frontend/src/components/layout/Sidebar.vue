<script setup>
  import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
  import { useTheme } from 'vuetify'
  import { useRouter, useRoute } from 'vue-router'
  import { useSidebarMenu } from '@/composables/useSidebarMenu'
  import { SIDEBAR_MENU } from '@/config/sidebarMenu'
  import SidebarFooter from './SidebarFooter.vue'

  // Files in /public are served from the site root — use URL strings,
  // don't import them (Vite doesn't support importing from /public).
  const LOGOS = {
    light: '/logo_dark.png', // dark logo for light backgrounds
    dark: '/logo_white.png', // white logo for dark backgrounds
    rail: '/logo_rail.png' // small square mark for the collapsed rail
  }

  const RAIL_WIDTH = 64
  const DRAWER_WIDTH = 260

  const props = defineProps({
    user: Object,
    rail: Boolean,
    buName: String,
    branchName: String,
    roleName: String,
    // 'classic'      → permanent; logo lives in the AppBar
    // 'icon_rail'    → permanent rail; logo lives in the AppBar
    // 'sidebar_only' → permanent; brand header + footer actions, no AppBar
    // 'pos_focus'    → temporary overlay; brand header + footer actions
    variant: { type: String, default: 'classic' },
    // Only used for variant="pos_focus" (open state of the overlay)
    modelValue: Boolean
  })

  const emit = defineEmits(['update:modelValue'])

  const router = useRouter()
  const route = useRoute()
  const theme = useTheme()

  const isDark = computed(() => theme.global.current.value.dark)
  // Logo shows in the sidebar for ALL layouts (it no longer lives in the AppBar)
  const showBrand = true
  // Footer actions (branch, language, user) only when there is no AppBar
  const showChrome = computed(() =>
    ['sidebar_only', 'pos_focus'].includes(props.variant)
  )
  const isTemporary = computed(() => props.variant === 'pos_focus')

  // The rail never applies to the temporary POS-focus drawer
  const isRail = computed(() => props.rail && !isTemporary.value)

  const brandLogo = computed(() => {
    if (isRail.value) return LOGOS.rail
    return isDark.value ? LOGOS.dark : LOGOS.light
  })

  function goHome() {
    router.push('/')
    if (isTemporary.value) emit('update:modelValue', false)
  }

  async function handleLogout() {
    try {
      await authService.logout() // tell the server
    } catch (e) {
      // ignore — still log out locally
    } finally {
      localStorage.removeItem('token') // use your real token key
      localStorage.removeItem('user')
      if (isTemporary.value) emit('update:modelValue', false)
      router.replace('/login')
    }
  }

  const { filteredMenu, openGroups, isGroupActive } =
    useSidebarMenu(SIDEBAR_MENU)

  // ── Flyout (rail mode) ────────────────────────────────────────────────
  const FLYOUT_MAX_HEIGHT = 420
  const flyout = ref({ visible: false, group: null, top: 0 })

  function showFlyout(group, event) {
    if (flyout.value.visible && flyout.value.group?.key === group.key) {
      hideFlyout()
      return
    }
    const rect = event.currentTarget.getBoundingClientRect()
    // Keep the panel inside the viewport
    const maxTop = window.innerHeight - FLYOUT_MAX_HEIGHT - 8
    const top = Math.max(8, Math.min(rect.top, maxTop))
    flyout.value = { visible: true, group, top }
  }

  function hideFlyout() {
    if (flyout.value.visible)
      flyout.value = { visible: false, group: null, top: 0 }
  }

  function isRailGroupHighlighted(group) {
    return (
      (flyout.value.visible && flyout.value.group?.key === group.key) ||
      isGroupActive(group)
    )
  }

  function onKeydown(e) {
    if (e.key === 'Escape') hideFlyout()
  }

  // Close the flyout when navigating, leaving rail mode, scrolling or resizing
  watch(() => route.fullPath, hideFlyout)
  watch(isRail, v => {
    if (!v) hideFlyout()
  })
  // Close the POS-focus overlay after navigating
  watch(
    () => route.fullPath,
    () => {
      if (isTemporary.value && props.modelValue)
        emit('update:modelValue', false)
    }
  )

  onMounted(() => {
    window.addEventListener('keydown', onKeydown)
    window.addEventListener('resize', hideFlyout)
    window.addEventListener('scroll', hideFlyout, true)
  })
  onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown)
    window.removeEventListener('resize', hideFlyout)
    window.removeEventListener('scroll', hideFlyout, true)
  })
</script>

<template>
  <v-navigation-drawer
    :rail="isRail"
    :rail-width="RAIL_WIDTH"
    :width="DRAWER_WIDTH"
    :permanent="!isTemporary"
    :temporary="isTemporary"
    :model-value="isTemporary ? modelValue : true"
    elevation="0"
    class="border-e"
    @update:model-value="v => emit('update:modelValue', v)"
  >
    <!-- Brand header: Sidebar-only / POS-focus (Classic & Icon-rail show it in the AppBar) -->
    <div
      v-if="showBrand"
      class="sidebar-brand"
      :class="{ 'sidebar-brand--rail': isRail }"
    >
      <button
        type="button"
        class="platform-logo-btn"
        aria-label="Go to home"
        @click="goHome"
      >
        <img
          :src="brandLogo"
          :class="isRail ? 'logo-rail' : 'logo-full'"
          alt="Platform logo"
          draggable="false"
        />
      </button>
    </div>

    <!-- MENU -->
    <v-list
      v-model:opened="openGroups"
      nav
      density="compact"
      class="sidebar-list"
    >
      <template v-for="link in filteredMenu" :key="link.key">
        <!-- Settings pinned last, separated from the working groups -->
        <v-divider v-if="link.key === 'system'" class="my-2 mx-2" />

        <!-- ── SINGLE ITEM ── -->
        <template v-if="!link.children">
          <v-tooltip :disabled="!isRail" location="end" :text="link.title">
            <template #activator="{ props: tip }">
              <v-list-item
                v-bind="tip"
                :to="!link.newTab ? link.path : undefined"
                :href="link.newTab ? link.path : undefined"
                :target="link.newTab ? '_blank' : undefined"
                :rel="link.newTab ? 'noopener' : undefined"
                :prepend-icon="link.icon"
                :title="isRail ? undefined : link.title"
                :aria-label="link.title"
                rounded="lg"
                class="mb-1"
                active-class="active-item"
              />
            </template>
          </v-tooltip>
        </template>

        <!-- ── GROUP, RAIL: icon opens a flyout ── -->
        <template v-else-if="isRail">
          <v-tooltip
            location="end"
            :text="link.title"
            :disabled="flyout.visible"
          >
            <template #activator="{ props: tip }">
              <v-list-item
                v-bind="tip"
                :prepend-icon="link.icon"
                :aria-label="link.title"
                :aria-expanded="
                  flyout.visible && flyout.group?.key === link.key
                "
                aria-haspopup="menu"
                rounded="lg"
                class="mb-1 rail-group-trigger"
                :class="{
                  'active-item': isRailGroupHighlighted(link),
                  'pos-group-trigger': link.emphasize
                }"
                @click="showFlyout(link, $event)"
              />
            </template>
          </v-tooltip>
        </template>

        <!-- ── GROUP, EXPANDED: accordion ── -->
        <v-list-group v-else :value="link.key">
          <template #activator="{ props: groupProps }">
            <v-list-item
              v-bind="groupProps"
              :prepend-icon="link.icon"
              :title="link.title"
              rounded="lg"
              class="mb-1"
              :class="{ 'pos-group-header': link.emphasize }"
            />
          </template>

          <v-list-item
            v-for="sublink in link.children"
            :key="sublink.key"
            :to="!sublink.newTab ? sublink.path : undefined"
            :href="sublink.newTab ? sublink.path : undefined"
            :target="sublink.newTab ? '_blank' : undefined"
            :rel="sublink.newTab ? 'noopener' : undefined"
            :prepend-icon="sublink.icon"
            :title="sublink.title"
            density="compact"
            class="sub-item"
            active-class="active-item"
          >
            <template v-if="sublink.badge" #append>
              <v-chip
                size="x-small"
                :color="sublink.badge === 'Mart' ? 'indigo' : 'blue'"
                variant="tonal"
                rounded="lg"
                class="ml-2"
              >
                {{ sublink.badge }}
              </v-chip>
            </template>
          </v-list-item>
        </v-list-group>
      </template>
    </v-list>

    <!-- Footer actions: Sidebar-only / POS-focus -->
    <template v-if="showChrome" #append>
      <SidebarFooter
        :user="user"
        :role-name="roleName"
        :rail="isRail"
        @logout="handleLogout"
      />
    </template>
  </v-navigation-drawer>

  <!-- ── RAIL FLYOUT ── -->
  <Teleport to="body">
    <div v-if="flyout.visible" class="flyout-overlay" @click="hideFlyout" />

    <Transition name="flyout">
      <div
        v-if="flyout.visible && flyout.group"
        class="rail-flyout"
        :class="{ 'pos-flyout': flyout.group.emphasize }"
        :style="{
          top: flyout.top + 'px',
          left: RAIL_WIDTH + 6 + 'px',
          maxHeight: FLYOUT_MAX_HEIGHT + 'px'
        }"
        role="menu"
        :aria-label="flyout.group.title"
      >
        <div class="flyout-header">{{ flyout.group.title }}</div>

        <v-list density="compact" nav class="flyout-list">
          <v-list-item
            v-for="sub in flyout.group.children"
            :key="sub.key"
            :to="!sub.newTab ? sub.path : undefined"
            :href="sub.newTab ? sub.path : undefined"
            :target="sub.newTab ? '_blank' : undefined"
            :rel="sub.newTab ? 'noopener' : undefined"
            :prepend-icon="sub.icon"
            :title="sub.title"
            role="menuitem"
            rounded="lg"
            active-class="active-item"
            @click="hideFlyout"
          >
            <template v-if="sub.badge" #append>
              <v-chip
                size="x-small"
                :color="sub.badge === 'Mart' ? 'indigo' : 'blue'"
                variant="tonal"
                rounded="lg"
              >
                {{ sub.badge }}
              </v-chip>
            </template>
          </v-list-item>
        </v-list>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
  /* ── Brand header ── */
  .sidebar-brand {
    height: 64px; /* same height as the AppBar */
    display: flex;
    align-items: center;
    padding: 0 16px;
    border-bottom: 1px solid
      rgba(var(--v-border-color), var(--v-border-opacity));
  }
  .sidebar-brand--rail {
    justify-content: center;
    padding: 0;
  }
  .platform-logo-btn {
    border: 0;
    background: transparent;
    padding: 4px;
    border-radius: 8px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
  }
  .platform-logo-btn:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }
  .logo-full {
    height: 28px;
    width: auto;
    max-width: 180px;
    object-fit: contain;
    display: block;
  }
  .logo-rail {
    width: 32px;
    height: 32px;
    object-fit: contain;
    display: block;
  }

  /* ── Remove Vuetify nav spacing (items already use .mb-1) ── */
  .sidebar-list:deep(.v-list-item:not(:first-child)),
  .sidebar-list:deep(.v-list-group:not(:first-child) > .v-list-item),
  .sidebar-list:deep(.v-list-group__items > .v-list-item),
  .sidebar-list:deep(.v-list-group__items > .v-list-group) {
    margin-top: 0;
  }

  /* ── Sub-items: thin guide line instead of a grey block ── */
  :deep(.v-list-group__items) {
    margin: 2px 0 6px 20px;
    padding-inline-start: 12px;
    border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }
  :deep(.v-list-group__items .v-list-item) {
    padding-inline-start: 4px !important;
    background-color: transparent !important;
    min-height: 36px;
  }
  .sub-item :deep(.v-list-item-title) {
    font-size: 0.8125rem !important;
    opacity: 0.85;
  }
  .sub-item :deep(.v-list-item__prepend > .v-icon) {
    font-size: 18px;
    opacity: 0.8;
  }

  /* ── Footer ── */
  .sidebar-footer {
    padding: 8px;
  }

  /* ── Active item (sidebar + flyout) ── */
  :deep(.v-list-item) {
    transition:
      background-color 0.15s ease,
      color 0.15s ease;
  }
  .active-item {
    background: rgba(var(--v-theme-primary), 0.1) !important;
    color: rgb(var(--v-theme-primary)) !important;
    position: relative;
  }
  .active-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 20%;
    bottom: 20%;
    width: 4px;
    background: rgb(var(--v-theme-primary));
    border-radius: 0 4px 4px 0;
  }

  /* ── POS group emphasis ── */
  .pos-group-header,
  .pos-group-trigger {
    background: rgba(var(--v-theme-primary), 0.08) !important;
    border: 1px solid rgba(var(--v-theme-primary), 0.18);
  }
  .pos-group-header :deep(.v-list-item-title),
  .pos-group-header :deep(.v-icon),
  .pos-group-trigger :deep(.v-icon) {
    color: rgb(var(--v-theme-primary));
    font-weight: 700;
  }

  /* ── Rail: centered icons ── */
  .rail-group-trigger {
    cursor: pointer;
  }
  :deep(.v-navigation-drawer--rail .v-list-item) {
    padding-inline: 0 !important;
    justify-content: center;
  }
  :deep(.v-navigation-drawer--rail .v-list-item__prepend) {
    margin-inline-end: 0 !important;
  }
  :deep(.v-navigation-drawer--rail .v-list-item__spacer) {
    display: none;
  }

  /* ── Flyout ── */
  .flyout-overlay {
    position: fixed;
    inset: 0;
    z-index: 1999;
  }
  .rail-flyout {
    position: fixed;
    z-index: 2000;
    min-width: 220px;
    overflow-y: auto;
    background: rgb(var(--v-theme-surface));
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 12px;
    box-shadow:
      0 4px 6px -1px rgba(0, 0, 0, 0.1),
      0 10px 24px -4px rgba(0, 0, 0, 0.12);
    padding: 4px 6px 6px;
  }
  .pos-flyout {
    border-color: rgba(var(--v-theme-primary), 0.25);
  }
  .flyout-header {
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(var(--v-theme-on-surface), 0.55);
    padding: 8px 12px 4px;
  }
  .pos-flyout .flyout-header {
    color: rgb(var(--v-theme-primary));
    font-weight: 700;
  }
  .flyout-list {
    background: transparent;
  }
  .rail-flyout :deep(.v-list-item-title) {
    font-size: 0.875rem !important;
  }
  .rail-flyout .active-item::before {
    display: none;
  }

  /* ── Transition ── */
  .flyout-enter-active,
  .flyout-leave-active {
    transition:
      opacity 0.15s ease,
      transform 0.15s ease;
  }
  .flyout-enter-from,
  .flyout-leave-to {
    opacity: 0;
    transform: translateX(-6px) scale(0.97);
  }
</style>
