<script setup>
  import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
  import { useTheme } from 'vuetify'
  import { useI18n } from 'vue-i18n'
  import { useRouter, useRoute } from 'vue-router'
  import { useSidebarMenu } from '@/composables/useSidebarMenu'
  import { SIDEBAR_MENU } from '@/config/sidebarMenu'
  import { LOGOS } from '@/config/branding'
  import { useSidebarCollapse } from '@/composables/useSidebarCollapse'
  import { SIDEBAR_RAIL_WIDTH, SIDEBAR_EXPANDED_WIDTH } from '@/config/sidebarLayout'
  import SidebarFooter from './SidebarFooter.vue'
  import ShopBranchSwitcher from './ShopBranchSwitcher.vue'

  const RAIL_WIDTH = SIDEBAR_RAIL_WIDTH
  const DRAWER_WIDTH = SIDEBAR_EXPANDED_WIDTH

  const props = defineProps({
    user: Object,
    buName: String,
    branchName: String,
    roleName: String,
    // 'classic'      → permanent; logo lives in the AppBar
    // 'icon_rail'    → permanent; logo lives in the AppBar
    // 'sidebar_only' → permanent; brand header + footer actions, no AppBar
    // 'pos_focus'    → temporary overlay; brand header only — same AppBar
    //                  as Classic/Icon-rail handles branch/language/user
    //                  (see Layout.vue), just with the drawer always
    //                  temporary instead of permanent
    variant: { type: String, default: 'classic' },
    // Open state of the temporary overlay (POS-focus always, every layout
    // below the lg breakpoint — see useSidebarCollapse's isTemporary)
    modelValue: Boolean
  })

  const emit = defineEmits(['update:modelValue'])

  const router = useRouter()
  const route = useRoute()
  const theme = useTheme()
  const { t } = useI18n()
  const variantRef = computed(() => props.variant)
  const { isTemporary, isCollapsed: isRail, toggleCollapse } = useSidebarCollapse(variantRef)

  const isDark = computed(() => theme.global.current.value.dark)
  // Logo shows in the sidebar for ALL layouts (it no longer lives in the AppBar)
  const showBrand = true
  // Footer actions (branch, language, user) only when there is no AppBar —
  // POS-focus has one (reused from Classic/Icon-rail, see Layout.vue), so
  // it's excluded here same as those two.
  const showChrome = computed(() => props.variant === 'sidebar_only')
  const isPosFocus = computed(() => props.variant === 'pos_focus')
  // POS-focus's drawer is narrower (280px) than the other permanent-layout
  // drawers (260px), per its own design.
  const drawerWidth = computed(() => (isPosFocus.value ? 280 : DRAWER_WIDTH))

  const brandLogo = computed(() => {
    if (isRail.value) return LOGOS.rail
    return isDark.value ? LOGOS.dark : LOGOS.light
  })

  function goHome() {
    router.push('/')
    if (isTemporary.value) emit('update:modelValue', false)
  }

  function closeDrawer() {
    emit('update:modelValue', false)
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

  // Rail single items are plain links, not Vuetify <v-list-item>, so they
  // don't get router-link's own active-class handling — compute it directly
  // (exact match; these are leaf items, no nested sub-routes to account for).
  function isLinkActive(link) {
    return route.path === link.path
  }

  function onKeydown(e) {
    if (e.key === 'Escape') hideFlyout()
    if (!isTemporary.value && (e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b') {
      e.preventDefault()
      toggleCollapse()
    }
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
    :width="drawerWidth"
    :permanent="!isTemporary"
    :temporary="isTemporary"
    :model-value="isTemporary ? modelValue : true"
    elevation="0"
    class="border-e"
    @update:model-value="v => emit('update:modelValue', v)"
  >
    <!-- Brand header: logo, every layout -->
    <div
      v-if="showBrand"
      class="sidebar-brand"
      :class="{ 'sidebar-brand--rail': isRail, 'sidebar-brand--pos-focus': isPosFocus }"
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

      <button
        v-if="isPosFocus"
        type="button"
        class="drawer-close-btn"
        :aria-label="t('pos_focus.close_menu')"
        @click="closeDrawer"
      >
        <v-icon icon="mdi-close" size="18" />
      </button>
    </div>

    <!-- Branch switcher: Sidebar-only only (Classic/Icon-rail/POS-focus all
         show it in the AppBar instead) -->
    <div
      v-if="showChrome && buName"
      class="sidebar-branch"
      :class="{ 'sidebar-branch--rail': isRail }"
    >
      <ShopBranchSwitcher :rail="isRail" />
    </div>

    <!-- MENU: RAIL — plain HTML full-width rows, Vuetify only for the
         tooltip and the flyout it opens (see Teleport block below) -->
    <nav v-if="isRail" class="rail-nav" aria-label="Primary">
      <template v-for="link in filteredMenu" :key="link.key">
        <div v-if="link.key === 'system'" class="rail-divider-line" />

        <!-- ── SINGLE ITEM ── -->
        <v-tooltip v-if="!link.children" location="end" :text="link.title">
          <template #activator="{ props: tip }">
            <router-link
              v-if="!link.newTab"
              v-bind="tip"
              :to="link.path"
              class="rail-row"
              :class="{ 'rail-row--active': isLinkActive(link) }"
              :aria-label="link.title"
            >
              <v-icon :icon="link.icon" size="22" />
            </router-link>
            <a
              v-else
              v-bind="tip"
              :href="link.path"
              target="_blank"
              rel="noopener"
              class="rail-row"
              :aria-label="link.title"
            >
              <v-icon :icon="link.icon" size="22" />
            </a>
          </template>
        </v-tooltip>

        <!-- ── GROUP: icon opens the flyout ── -->
        <v-tooltip
          v-else
          location="end"
          :text="link.title"
          :disabled="flyout.visible"
        >
          <template #activator="{ props: tip }">
            <button
              type="button"
              v-bind="tip"
              class="rail-row"
              :class="{
                'rail-row--active': isRailGroupHighlighted(link),
                'rail-row--pos': link.emphasize
              }"
              :aria-label="link.title"
              :aria-expanded="
                flyout.visible && flyout.group?.key === link.key
              "
              aria-haspopup="menu"
              @click="showFlyout(link, $event)"
            >
              <v-icon :icon="link.icon" size="22" />
            </button>
          </template>
        </v-tooltip>
      </template>
    </nav>

    <!-- MENU: EXPANDED — unchanged -->
    <v-list
      v-else
      v-model:opened="openGroups"
      nav
      density="compact"
      class="sidebar-list"
    >
      <template v-for="link in filteredMenu" :key="link.key">
        <!-- Settings pinned last, separated from the working groups -->
        <v-divider v-if="link.key === 'system'" class="my-2 mx-2" />

        <!-- ── SINGLE ITEM ── -->
        <v-list-item
          v-if="!link.children"
          :to="!link.newTab ? link.path : undefined"
          :href="link.newTab ? link.path : undefined"
          :target="link.newTab ? '_blank' : undefined"
          :rel="link.newTab ? 'noopener' : undefined"
          :prepend-icon="link.icon"
          :title="link.title"
          :aria-label="link.title"
          rounded="lg"
          class="mb-1"
          active-class="active-item"
        />

        <!-- ── GROUP: accordion ── -->
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
        :bu-name="buName"
        :branch-name="branchName"
        :role-name="roleName"
        :rail="isRail"
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
  .sidebar-brand--pos-focus {
    height: 52px;
    justify-content: space-between;
  }
  .drawer-close-btn {
    width: 32px;
    height: 32px;
    flex: none;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: rgba(var(--v-theme-on-surface), 0.7);
    cursor: pointer;
  }
  .drawer-close-btn:hover {
    background: rgba(var(--v-theme-on-surface), 0.06);
  }
  .drawer-close-btn:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
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

  /* ── Branch switcher (Sidebar-only / POS-focus) ── */
  .sidebar-branch {
    padding: 8px;
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }
  .sidebar-branch--rail {
    padding: 0;
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

  /* ── POS group emphasis (expanded accordion header) ── */
  .pos-group-header {
    background: rgba(var(--v-theme-primary), 0.08) !important;
    border: 1px solid rgba(var(--v-theme-primary), 0.18);
  }
  .pos-group-header :deep(.v-list-item-title),
  .pos-group-header :deep(.v-icon) {
    color: rgb(var(--v-theme-primary));
    font-weight: 700;
  }

  /* ── Rail: plain-HTML full-width rows ──────────────────────────────────
     Not Vuetify <v-list-item> — a bare CSS grid component whose "content"
     area is still rendered (just empty) and keeps claiming width as a 1fr
     track no matter what CSS is thrown at it, which is what made icons look
     off-center before. Plain buttons/links sidestep that entirely. */
  .rail-nav {
    display: flex;
    flex-direction: column;
    padding: 8px 0;
  }
  .rail-row {
    position: relative;
    width: 100%;
    height: 48px;
    padding: 0;
    margin: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(var(--v-theme-on-surface), 0.7);
    cursor: pointer;
    text-decoration: none;
    transition: background-color 0.15s ease, color 0.15s ease;
  }
  .rail-row:hover {
    background: rgba(var(--v-theme-on-surface), 0.05);
  }
  .rail-row:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: -2px;
  }
  .rail-row--active {
    background: rgba(var(--v-theme-primary), 0.1);
    color: rgb(var(--v-theme-primary));
  }
  .rail-row--active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 8px;
    bottom: 8px;
    width: 3px;
    background: rgb(var(--v-theme-primary));
    border-radius: 0 3px 3px 0;
  }
  .rail-row--pos {
    color: rgb(var(--v-theme-primary));
  }
  .rail-divider-line {
    height: 1px;
    margin: 8px 12px;
    background: rgba(var(--v-border-color), var(--v-border-opacity));
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
