<script setup>
  import { ref } from 'vue'
  import { useSidebarMenu } from '@/composables/useSidebarMenu'
  import { SIDEBAR_MENU } from '@/config/sidebarMenu'
  import BranchSwitcher from './BranchSwitcher.vue'

  defineProps({
    user: Object,
    rail: Boolean
  })

  const { filteredMenu, openGroups, isGroupActive, isLeafActive } = useSidebarMenu(SIDEBAR_MENU)

  // ── Flyout state (rail mode) ──────────────────────────────────────────────────
  const flyout = ref({ visible: false, group: null, anchorY: 0 })

  function showFlyout(group, event) {
    if (flyout.value.visible && flyout.value.group?.key === group.key) {
      hideFlyout()
      return
    }
    const el = event.currentTarget
    const rect = el.getBoundingClientRect()
    flyout.value = { visible: true, group, anchorY: rect.top }
  }

  function hideFlyout() {
    flyout.value = { visible: false, group: null, anchorY: 0 }
  }

  function isRailGroupHighlighted(group) {
    const flyoutOpen =
      flyout.value.visible && flyout.value.group?.key === group.key
    return flyoutOpen || isGroupActive(group)
  }

</script>

<template>
  <v-navigation-drawer
    :rail="rail"
    permanent
    elevation="0"
    class="border-right"
    width="260"
  >
    <!-- BRANCH SWITCHER — pinned at the very top, only shown when there's an
         actual choice to make (owner, multi-branch tenant). -->
    <BranchSwitcher :rail="rail" />

    <!-- MENU -->
    <v-list v-model:opened="openGroups" nav density="compact" class="sidebar-list">
      <div v-for="link in filteredMenu" :key="link.key">
        <!-- Settings is pinned last, visually separated from the working groups -->
        <v-divider v-if="link.key === 'system'" class="my-2 mx-2" />

        <!-- ── SINGLE ITEM ───────────────────────────────────────────────── -->
        <template v-if="!link.children">
          <v-tooltip v-if="rail" location="right">
            <template #activator="{ props }">
              <v-list-item
                v-bind="props"
                :to="!link.newTab ? link.path : undefined"
                :href="link.newTab ? link.path : undefined"
                :target="link.newTab ? '_blank' : undefined"
                :prepend-icon="link.icon"
                rounded="lg"
                class="mb-1"
                active-class="active-item"
              />
            </template>
            <span>{{ link.title }}</span>
          </v-tooltip>

          <v-list-item
            v-else
            :to="!link.newTab ? link.path : undefined"
            :href="link.newTab ? link.path : undefined"
            :target="link.newTab ? '_blank' : undefined"
            :prepend-icon="link.icon"
            :title="link.title"
            rounded="lg"
            class="mb-1"
            active-class="active-item"
          />
        </template>

        <!-- ── GROUP — RAIL MODE: icon triggers flyout ──────────────────── -->
        <template v-else-if="rail">
          <v-list-item
            :prepend-icon="link.icon"
            rounded="lg"
            class="mb-1 rail-group-trigger"
            :class="{
              'active-item': isRailGroupHighlighted(link),
              'pos-group-trigger': link.emphasize
            }"
            @click="showFlyout(link, $event)"
          />
        </template>

        <!-- ── GROUP — EXPANDED MODE: normal accordion ───────────────────── -->
        <v-list-group v-else :value="link.key">
          <template #activator="{ props }">
            <v-list-item
              v-bind="props"
              :prepend-icon="link.icon"
              :title="link.title"
              rounded="lg"
              class="mb-1"
              :class="{ 'pos-group-header': link.emphasize }"
            />
          </template>

          <template v-for="sublink in link.children" :key="sublink.key">
            <v-list-item
              :to="!sublink.newTab ? sublink.path : undefined"
              :href="sublink.newTab ? sublink.path : undefined"
              :target="sublink.newTab ? '_blank' : undefined"
              :active="sublink.path?.includes('?') ? isLeafActive(sublink) : undefined"
              :prepend-icon="sublink.icon"
              :title="sublink.title"
              density="compact"
              class="sub-item"
              active-class="active-item"
            >
              <template #append>
                <v-chip
                  v-if="sublink.badge"
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
          </template>
        </v-list-group>
      </div>
    </v-list>

  </v-navigation-drawer>

  <!-- ── RAIL FLYOUT SUBMENU ──────────────────────────────────────────────────── -->
  <Teleport to="body">
    <Transition name="flyout">
      <div
        v-if="flyout.visible && flyout.group"
        class="rail-flyout"
        :class="{ 'pos-flyout': flyout.group.emphasize }"
        :style="{ top: flyout.anchorY + 'px' }"
      >
        <!-- @mouseleave="hideFlyout" -->
        <!-- Group title -->
        <div class="flyout-header">{{ flyout.group.title }}</div>

        <!-- Sub items using v-list -->
        <v-list density="compact" nav>
          <v-list-item
            v-for="sub in flyout.group.children"
            :key="sub.key"
            :to="sub.path"
            :active="sub.path?.includes('?') ? isLeafActive(sub) : undefined"
            :prepend-icon="sub.icon"
            :title="sub.title"
            rounded="lg"
            active-class="active-item"
            @click="hideFlyout"
          >
            <template #append>
              <v-chip
                v-if="sub.badge"
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

    <div v-if="flyout.visible" class="flyout-overlay" @click="hideFlyout" />
  </Teleport>
</template>

<style scoped>
  /* ── Kill Vuetify's built-in `.v-list--nav` item spacing (4px margin-top
   * on every item/group after the first) — this sidebar already spaces
   * items via `.mb-1` on each v-list-item, so the two stacked. */
  .sidebar-list:deep(.v-list-item:not(:first-child)),
  .sidebar-list:deep(.v-list-group:not(:first-child) > .v-list-item),
  .sidebar-list:deep(.v-list-group__items > .v-list-item),
  .sidebar-list:deep(.v-list-group__items > .v-list-group) {
    margin-top: 0;
  }

  /* ── Expanded sidebar: sub-item indent & font ─────────────────────────────
   * Matches photo-studio-saas's DefaultLayout.vue treatment — a thin guide
   * line close to the parent instead of Vuetify's default full-width
   * gray-background block indent, which reads as heavier/wider than a
   * modern sidebar wants. */
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

  /* ── Version footer ─────────────────────────────────────────────────────── */
  .version {
    background: rgba(var(--v-theme-primary), 0.1) !important;
  }

  .text-overline {
    color: rgba(var(--v-theme-on-surface), 0.87);
  }

  /* ── Active item (sidebar + flyout share this) ──────────────────────────── */
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

  /* ── POS group — visually emphasized as the primary workspace ──────────── */
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

  .pos-flyout {
    border: 1px solid rgba(var(--v-theme-primary), 0.18);
  }

  .pos-flyout .flyout-header {
    color: rgb(var(--v-theme-primary));
    font-weight: 700;
  }

  /* ── Rail: center icons, strip extra padding ────────────────────────────── */
  .rail-group-trigger {
    cursor: pointer;
  }

  :deep(.v-navigation-drawer--rail .v-list-item) {
    padding-inline-start: 0 !important;
    padding-inline-end: 0 !important;
    justify-content: center;
  }

  :deep(.v-navigation-drawer--rail .v-list-item__prepend) {
    margin-inline-end: 0 !important;
  }

  /* ── Flyout overlay ─────────────────────────────────────────────────────── */
  .flyout-overlay {
    position: fixed;
    inset: 0;
    z-index: 1999;
  }

  /* ── Flyout panel ───────────────────────────────────────────────────────── */
  .rail-flyout {
    position: fixed;
    left: 60px;
    z-index: 2000;
    min-width: 220px;
    background: rgb(var(--v-theme-surface));
    border-radius: 12px;
    box-shadow:
      0 4px 6px -1px rgba(0, 0, 0, 0.1),
      0 10px 24px -4px rgba(0, 0, 0, 0.12);
    padding: 4px 6px 6px;
    overflow: hidden;
  }

  /* Flyout group label */
  .flyout-header {
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(var(--v-theme-on-surface), 0.45);
    padding: 8px 12px 4px;
  }

  /* Flyout v-list-item title sizing */
  .rail-flyout :deep(.v-list-item-title) {
    font-size: 0.875rem !important;
  }

  /* Flyout active-item: suppress the left bar (too wide for a popover) */
  .rail-flyout .active-item::before {
    display: none;
  }

  /* ── Transition ─────────────────────────────────────────────────────────── */
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
