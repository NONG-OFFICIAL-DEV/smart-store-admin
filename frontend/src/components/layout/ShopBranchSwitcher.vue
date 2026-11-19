<template>
  <div v-if="authStore.bu_name" class="shop-switcher">
    <v-menu v-if="canSwitch" :close-on-content-click="false">
      <template #activator="{ props: menuProps }">
        <button type="button" class="shop-switcher__btn" v-bind="menuProps">
          <ShopAvatar :logo-url="authStore.logo_url" :name="authStore.bu_name" />
          <span class="shop-switcher__text">
            <span class="shop-switcher__name">{{ authStore.bu_name }}</span>
            <span class="shop-switcher__branch">{{ activeBranchName || t('app_bar.select_branch') }}</span>
          </span>
          <v-icon icon="mdi-chevron-down" size="18" />
        </button>
      </template>

      <v-card rounded="lg" min-width="260">
        <div class="pa-3 d-flex align-center ga-3">
          <ShopAvatar :logo-url="authStore.logo_url" :name="authStore.bu_name" :size="36" />
          <div class="min-w-0">
            <div class="text-body-2 font-weight-bold text-truncate">{{ authStore.bu_name }}</div>
            <div class="text-caption text-medium-emphasis">{{ t('app_bar.current_shop') }}</div>
          </div>
        </div>
        <v-divider />
        <div class="px-3 pt-2 pb-1 text-caption text-medium-emphasis font-weight-medium">
          {{ t('app_bar.switch_branch') }}
        </div>
        <v-list density="compact" nav class="pb-2">
          <v-list-item
            v-for="b in branchStore.branches"
            :key="b.id"
            rounded="lg"
            class="mx-2"
            @click="select(b.id)"
          >
            <template #prepend>
              <span
                class="status-dot"
                :class="b.is_open ? 'status-dot--open' : 'status-dot--closed'"
                :title="b.is_open ? t('app_bar.branch_open') : t('app_bar.branch_closed')"
              />
            </template>
            <v-list-item-title>{{ b.name }}</v-list-item-title>
            <template #append>
              <v-icon
                v-if="b.id === authStore.activeBranchId"
                icon="mdi-check"
                size="18"
                color="primary"
              />
            </template>
          </v-list-item>
        </v-list>
      </v-card>
    </v-menu>

    <!-- Fixed context (staff pinned to one branch, or a single-branch
         tenant) — same info, no switching affordance to open. -->
    <div v-else class="shop-switcher__btn shop-switcher__btn--static">
      <ShopAvatar :logo-url="authStore.logo_url" :name="authStore.bu_name" />
      <span class="shop-switcher__text">
        <span class="shop-switcher__name">{{ authStore.bu_name }}</span>
        <span v-if="activeBranchName" class="shop-switcher__branch">{{ activeBranchName }}</span>
      </span>
    </div>
  </div>
</template>

<script setup>
  import { computed, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAuthStore } from '@/stores/authStore'
  import { useBranchStore } from '@/stores/branchStore'
  import ShopAvatar from './ShopAvatar.vue'

  const { t } = useI18n()
  const authStore = useAuthStore()
  const branchStore = useBranchStore()

  // Super admin has no branch concept; staff are pinned to one branch
  // (authStore.branch_id forces activeBranchId, see authStore.fetchMe()) —
  // only an owner/multi-branch context has anything to actually switch.
  const isFixed = computed(() => authStore.isSuperAdmin || !!authStore.branch_id)
  const canSwitch = computed(() => !isFixed.value && branchStore.branches.length > 1)

  const activeBranchName = computed(
    () =>
      authStore.branch_name ||
      branchStore.branches.find(b => b.id === authStore.activeBranchId)?.name
  )

  function select(id) {
    authStore.setActiveBranch(id)
  }

  onMounted(async () => {
    // Fetched for everyone with a real tenant context (this component only
    // ever mounts when authStore.bu_name is set — see the root v-if), not
    // just owners: a pinned-branch staff member still needs their one
    // branch's full record (customer_display_settings etc.) available for
    // the app bar's "Open customer display" menu item to read.
    if (branchStore.branches.length) return
    await branchStore.fetchBranches()

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

<style scoped>
  .shop-switcher__btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 4px 10px 4px 4px;
    border-radius: 999px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-family: inherit;
    transition: background-color 0.15s;
  }
  .shop-switcher__btn:hover {
    background: rgba(var(--v-theme-on-surface), 0.06);
  }
  .shop-switcher__btn--static {
    cursor: default;
  }
  .shop-switcher__btn--static:hover {
    background: transparent;
  }
  .shop-switcher__text {
    display: flex;
    flex-direction: column;
    text-align: start;
    line-height: 1.2;
    min-width: 0;
  }
  .shop-switcher__name {
    font-size: 13px;
    font-weight: 700;
    color: rgb(var(--v-theme-on-surface));
    max-width: 160px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .shop-switcher__branch {
    font-size: 12px;
    color: rgba(var(--v-theme-on-surface), 0.6);
    max-width: 160px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .min-w-0 {
    min-width: 0;
  }
  .status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
  }
  .status-dot--open {
    background: rgb(var(--v-theme-success));
  }
  .status-dot--closed {
    background: rgb(var(--v-theme-warning));
  }
</style>
