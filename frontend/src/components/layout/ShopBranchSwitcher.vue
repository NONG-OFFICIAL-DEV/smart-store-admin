<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRouter } from 'vue-router'
  import { useAuthStore } from '@/stores/authStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useAppUtils } from '@nong-official-dev/core'

  defineProps({
    // Compact icon-only row for the Sidebar-only/POS-focus sidebar footer,
    // where there's no horizontal space for the full trigger (see
    // SidebarFooter.vue — the only place those two layouts render chrome).
    rail: Boolean
  })

  const { t } = useI18n()
  const router = useRouter()
  const authStore = useAuthStore()
  const branchStore = useBranchStore()
  const { notif } = useAppUtils()

  // Super admin has no branch concept; staff are pinned to one branch
  // (authStore.branch_id forces activeBranchId, see authStore.fetchMe()) —
  // neither can switch. An owner can always reach Manage branches
  // (permission-gated below) even with just one branch today.
  const isFixed = computed(() => authStore.isSuperAdmin || !!authStore.branch_id)
  const canSwitch = computed(() => !isFixed.value && branchStore.branches.length > 1)
  const canManage = computed(() => authStore.can('branches.manage'))
  const canOpen = computed(() => !isFixed.value && (canSwitch.value || canManage.value))

  const activeBranch = computed(() => branchStore.branches.find(b => b.id === authStore.activeBranchId) ?? null)
  const activeBranchName = computed(() => activeBranch.value?.name || authStore.branch_name || '')

  const menuOpen = ref(false)
  const search = ref('')
  const showSearch = computed(() => branchStore.branches.length > 5)

  const filteredBranches = computed(() => {
    const q = search.value?.trim().toLowerCase()
    if (!q) return branchStore.branches
    return branchStore.branches.filter(b => b.name?.toLowerCase().includes(q))
  })

  const triggerRef = ref(null)
  const panelWidth = ref(300)
  function onMenuToggle(open) {
    if (!open) return
    search.value = ''
    const el = triggerRef.value
    if (el) panelWidth.value = Math.max(300, el.offsetWidth)
  }

  function initial(name) {
    return name?.trim()?.charAt(0)?.toUpperCase() || '?'
  }

  function select(b) {
    menuOpen.value = false
    if (b.id === authStore.activeBranchId) return
    authStore.setActiveBranch(b.id)
    notif(t('shop_switcher.switched_to', { branch: b.name }), { type: 'success' })
  }

  function goManageBranches() {
    menuOpen.value = false
    router.push({ name: 'settings-hub', query: { section: 'branches' } })
  }

  onMounted(async () => {
    // Fetched for everyone with a real tenant context (this component only
    // ever mounts when authStore.bu_name is set — see the root v-if), not
    // just owners: a pinned-branch staff member still needs their one
    // branch's full record (customer_display_settings etc.) available for
    // the app bar's "Open customer display" menu item to read. The full,
    // unpaginated list (perPage: -1) — same convention as the Branches
    // settings page — so the count/search here are always complete.
    if (branchStore.branches.length) return
    await branchStore.fetchBranches({ perPage: -1 })

    if (isFixed.value) return
    const fetched = branchStore.branches
    const activeStillValid = fetched.some(b => b.id === authStore.activeBranchId)
    // Covers both: nothing persisted yet (first time an owner lands here),
    // and a stale/foreign id left over in localStorage (e.g. the branch was
    // deleted, or another tenant's id on a shared device).
    if (!activeStillValid && fetched.length) {
      authStore.setActiveBranch(fetched[0].id)
    }
  })
</script>

<template>
  <div v-if="authStore.bu_name" class="shop-switcher">
    <v-menu
      v-if="canOpen"
      v-model="menuOpen"
      :close-on-content-click="false"
      location="bottom start"
      offset="8"
      @update:model-value="onMenuToggle"
    >
      <template #activator="{ props: menuProps }">
        <v-tooltip v-if="rail" location="end" :text="activeBranchName || t('shop_switcher.select_branch')">
          <template #activator="{ props: tip }">
            <button
              ref="triggerRef"
              type="button"
              class="switcher-trigger switcher-trigger--rail"
              v-bind="{ ...tip, ...menuProps }"
              :aria-label="t('shop_switcher.switch_branch')"
            >
              <span class="switcher-logo">
                <img v-if="authStore.logo_url" :src="authStore.logo_url" alt="" />
                <template v-else>{{ initial(authStore.bu_name) }}</template>
              </span>
            </button>
          </template>
        </v-tooltip>

        <button
          v-else
          ref="triggerRef"
          type="button"
          class="switcher-trigger"
          v-bind="menuProps"
          :aria-label="t('shop_switcher.switch_branch')"
        >
          <span class="switcher-logo">
            <img v-if="authStore.logo_url" :src="authStore.logo_url" alt="" />
            <template v-else>{{ initial(authStore.bu_name) }}</template>
          </span>
          <span class="switcher-text">
            <span class="switcher-name">{{ authStore.bu_name }}</span>
            <span class="switcher-branch">
              <span
                v-if="activeBranch"
                class="status-dot"
                :class="activeBranch.is_open ? 'status-dot--open' : 'status-dot--closed'"
              />
              {{ activeBranchName || t('shop_switcher.select_branch') }}
            </span>
          </span>
          <v-icon icon="mdi-unfold-more-horizontal" size="18" class="switcher-chevron" />
        </button>
      </template>

      <div class="switcher-panel" :style="{ width: panelWidth + 'px' }">
        <div v-if="showSearch" class="switcher-search">
          <v-icon icon="mdi-magnify" size="18" />
          <input v-model="search" type="text" :placeholder="t('shop_switcher.search_placeholder')" />
        </div>

        <div class="switcher-label">{{ t('shop_switcher.branches_label', { count: filteredBranches.length }) }}</div>

        <div class="switcher-rows" role="menu" :aria-label="t('shop_switcher.switch_branch')">
          <div
            v-for="b in filteredBranches"
            :key="b.id"
            class="branch-row"
            :class="{ 'branch-row--active': b.id === authStore.activeBranchId }"
            role="menuitemradio"
            :aria-checked="b.id === authStore.activeBranchId"
            tabindex="0"
            @click="select(b)"
            @keydown.enter="select(b)"
            @keydown.space.prevent="select(b)"
          >
            <span class="branch-badge" :class="{ 'branch-badge--current': b.id === authStore.activeBranchId }">
              {{ initial(b.name) }}
            </span>
            <span class="branch-info">
              <span class="branch-name">{{ b.name }}</span>
              <span class="branch-meta">
                <span class="status-dot status-dot--sm" :class="b.is_open ? 'status-dot--open' : 'status-dot--closed'" />
                {{ b.is_open ? t('branches.status.open') : t('branches.status.closed') }}
              </span>
            </span>
            <v-icon v-if="b.id === authStore.activeBranchId" icon="mdi-check" size="18" color="primary" />
          </div>

          <div v-if="!filteredBranches.length" class="switcher-empty">
            {{ t('shop_switcher.no_results', { query: search }) }}
          </div>
        </div>

        <div v-if="canManage" class="switcher-footer">
          <button type="button" class="footer-btn footer-btn--outlined" @click="goManageBranches">
            {{ t('shop_switcher.manage_branches') }}
          </button>
        </div>
      </div>
    </v-menu>

    <!-- Fixed context (staff pinned to one branch, super admin, or an
         owner with neither branches to switch nor permission to manage
         them) — same info, no menu to open. -->
    <v-tooltip v-else-if="rail" location="end" :text="activeBranchName || authStore.bu_name">
      <template #activator="{ props: tip }">
        <div class="switcher-trigger switcher-trigger--rail switcher-trigger--static" v-bind="tip">
          <span class="switcher-logo">
            <img v-if="authStore.logo_url" :src="authStore.logo_url" alt="" />
            <template v-else>{{ initial(authStore.bu_name) }}</template>
          </span>
        </div>
      </template>
    </v-tooltip>
    <div v-else class="switcher-trigger switcher-trigger--static">
      <span class="switcher-logo">
        <img v-if="authStore.logo_url" :src="authStore.logo_url" alt="" />
        <template v-else>{{ initial(authStore.bu_name) }}</template>
      </span>
      <span class="switcher-text">
        <span class="switcher-name">{{ authStore.bu_name }}</span>
        <span v-if="activeBranchName" class="switcher-branch">
          <span
            v-if="activeBranch"
            class="status-dot"
            :class="activeBranch.is_open ? 'status-dot--open' : 'status-dot--closed'"
          />
          {{ activeBranchName }}
        </span>
      </span>
    </div>
  </div>
</template>

<style scoped>
  /* ── Trigger ───────────────────────────────────────────────────────── */
  .switcher-trigger {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    min-height: 52px;
    padding: 8px 10px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 12px;
    background: rgb(var(--v-theme-surface));
    color: rgb(var(--v-theme-on-surface));
    cursor: pointer;
    text-align: start;
    font-family: inherit;
  }
  .switcher-trigger:hover {
    background: rgba(var(--v-theme-on-surface), 0.04);
  }
  .switcher-trigger:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }
  .switcher-trigger--static {
    cursor: default;
  }
  .switcher-trigger--static:hover {
    background: rgb(var(--v-theme-surface));
  }
  .switcher-trigger--rail {
    justify-content: center;
    min-height: 48px;
    padding: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
  }
  .switcher-trigger--rail:hover {
    background: rgba(var(--v-theme-on-surface), 0.05);
  }
  .switcher-trigger--rail:focus-visible {
    outline-offset: -2px;
  }

  .switcher-logo {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    flex: none;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: rgb(var(--v-theme-primary));
    color: rgb(var(--v-theme-on-primary));
    font-size: 14px;
    font-weight: 700;
  }
  .switcher-logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .switcher-text {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    line-height: 1.3;
  }
  .switcher-name {
    font-size: 14px;
    font-weight: 700;
    color: rgb(var(--v-theme-on-surface));
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .switcher-branch {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: rgba(var(--v-theme-on-surface), 0.6);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .switcher-chevron {
    flex: none;
    opacity: 0.6;
  }

  .status-dot {
    flex: none;
    width: 7px;
    height: 7px;
    border-radius: 50%;
  }
  .status-dot--sm {
    width: 6px;
    height: 6px;
  }
  .status-dot--open {
    background: rgb(var(--v-theme-success));
  }
  .status-dot--closed {
    background: rgb(var(--v-theme-warning));
  }

  /* ── Menu panel ────────────────────────────────────────────────────── */
  .switcher-panel {
    min-width: 300px;
    border-radius: 14px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    background: rgb(var(--v-theme-surface));
    color: rgb(var(--v-theme-on-surface));
    box-shadow:
      0 4px 6px -1px rgba(0, 0, 0, 0.1),
      0 10px 24px -4px rgba(0, 0, 0, 0.12);
    overflow: hidden;
  }

  .switcher-search {
    display: flex;
    align-items: center;
    gap: 8px;
    height: 38px;
    margin: 10px 12px 4px;
    padding: 0 10px;
    border-radius: 10px;
    background: rgba(var(--v-theme-on-surface), 0.05);
    color: rgba(var(--v-theme-on-surface), 0.6);
  }
  .switcher-search input {
    flex: 1;
    min-width: 0;
    border: 0;
    outline: none;
    background: transparent;
    font: 13px inherit;
    color: rgb(var(--v-theme-on-surface));
  }
  .switcher-search input::placeholder {
    color: rgba(var(--v-theme-on-surface), 0.5);
  }

  .switcher-label {
    padding: 10px 14px 4px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgba(var(--v-theme-on-surface), 0.55);
  }

  .switcher-rows {
    max-height: 320px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 2px 8px 8px;
  }

  .branch-row {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 52px;
    padding: 6px 10px;
    border-radius: 10px;
    cursor: pointer;
  }
  .branch-row:hover {
    background: rgba(var(--v-theme-on-surface), 0.05);
  }
  .branch-row:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: -2px;
  }
  .branch-row--active {
    background: rgba(var(--v-theme-primary), 0.08);
  }

  .branch-badge {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    flex: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    background: rgba(var(--v-theme-on-surface), 0.08);
    color: rgba(var(--v-theme-on-surface), 0.7);
  }
  .branch-badge--current {
    background: rgb(var(--v-theme-primary));
    color: rgb(var(--v-theme-on-primary));
  }

  .branch-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    line-height: 1.3;
  }
  .branch-name {
    font-size: 14px;
    font-weight: 600;
    color: rgb(var(--v-theme-on-surface));
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .branch-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: rgba(var(--v-theme-on-surface), 0.6);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .switcher-empty {
    padding: 24px 16px;
    text-align: center;
    font-size: 13px;
    color: rgba(var(--v-theme-on-surface), 0.6);
  }

  /* ── Footer ────────────────────────────────────────────────────────── */
  .switcher-footer {
    display: flex;
    gap: 8px;
    padding: 8px;
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }
  .footer-btn {
    flex: 1;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border-radius: 10px;
    font: 600 13px inherit;
    cursor: pointer;
  }
  .footer-btn--outlined {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    background: transparent;
    color: rgb(var(--v-theme-on-surface));
  }
  .footer-btn--outlined:hover {
    background: rgba(var(--v-theme-on-surface), 0.05);
  }
  .footer-btn:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }
</style>
