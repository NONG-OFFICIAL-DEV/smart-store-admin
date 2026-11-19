<template>
  <div class="app-bar-actions">
    <!-- Register status — only meaningful for staff (owners have no Staff
         row of their own, so there's nothing personal to open/close) -->
    <v-chip
      v-if="authStore.staff_id"
      size="small"
      variant="tonal"
      :color="registerOpen ? 'success' : 'default'"
      class="d-none d-md-inline-flex"
      rounded="lg"
      :prepend-icon="registerOpen ? 'mdi-circle' : 'mdi-circle-outline'"
      @click="openRegisterPanel"
    >
      {{ registerOpen ? t('cash_register.open_label') : t('cash_register.closed_label') }}
    </v-chip>

    <!-- Online status -->
    <v-chip
      size="small"
      variant="tonal"
      :color="isOnline ? 'success' : 'error'"
      rounded="lg"
      class="d-none d-sm-inline-flex"
      :prepend-icon="isOnline ? 'mdi-circle' : 'mdi-circle-outline'"
    >
      {{ isOnline ? t('app_bar.online') : t('app_bar.offline') }}
    </v-chip>

    <LanguageSwitch />

    <!-- Notifications -->
    <AppNotificationBell
      :ref="bell.bellRef"
      v-bind="bell.bellProps"
      @item-click="bell.goToNotifications"
      @view-all="bell.goToNotifications"
    />

    <!-- Avatar / user menu -->
    <v-menu
      v-model="userMenu"
      rounded="xl"
      min-width="230"
      offset="8"
      :close-on-content-click="false"
    >
      <template #activator="{ props: menuProps }">
        <button type="button" class="user-btn" v-bind="menuProps">
          <v-avatar :color="avatarColor" size="36" class="avatar-ring">
            <v-img v-if="user?.avatar_url" :src="user.avatar_url" cover />
            <span v-else class="text-caption font-weight-black text-white">
              {{ initials }}
            </span>
          </v-avatar>
        </button>
      </template>

      <v-card rounded="lg" elevation="1">
        <v-card-text class="pa-0">
          <!-- User info -->
          <div class="px-4 pt-4 pb-3">
            <div class="d-flex align-center gap-3">
              <v-avatar :color="avatarColor" size="48" rounded="lg">
                <v-img
                  v-if="user?.avatar_url"
                  :src="user.avatar_url"
                  cover
                />
                <span
                  v-else
                  class="text-body-2 font-weight-black text-white"
                >
                  {{ initials }}
                </span>
              </v-avatar>
              <div class="min-w-0 flex-1 overflow-hidden">
                <div class="text-body-2 font-weight-bold text-truncate">
                  {{ user?.first_name }} {{ user?.last_name }}
                </div>
                <div
                  class="text-caption text-medium-emphasis text-truncate"
                >
                  {{ user?.email }}
                </div>
              </div>
            </div>

            <!-- Badges -->
            <div class="d-flex gap-1 flex-wrap mt-3">
              <v-chip
                v-if="branchName"
                size="x-small"
                variant="tonal"
                color="primary"
                rounded="lg"
              >
                <v-icon start size="10" icon="mdi-store-outline" />
                {{ branchName }}
              </v-chip>
              <v-chip
                v-if="buName"
                size="x-small"
                variant="tonal"
                color="secondary"
                rounded="lg"
              >
                {{ buName }}
              </v-chip>
              <v-chip
                v-if="roleName"
                size="x-small"
                variant="tonal"
                color="secondary"
                rounded="lg"
              >
                <v-icon start size="10" icon="mdi-shield-account-outline" />
                {{ roleName }}
              </v-chip>
            </div>
          </div>

          <v-divider />

          <!-- Menu items -->
          <div class="pa-2">
            <v-list-item
              v-for="(item, index) in menuItems"
              :key="index"
              :prepend-icon="item.icon"
              :title="t(item.title)"
              rounded="lg"
              class="mb-1"
              link
              @click="item.action"
            >
              <template #append>
                <v-icon size="14" color="medium-emphasis">
                  mdi-chevron-right
                </v-icon>
              </template>
            </v-list-item>
          </div>

          <v-divider />

          <!-- Logout -->
          <div class="pa-3">
            <v-btn
              block
              variant="tonal"
              rounded="lg"
              color="error"
              prepend-icon="mdi-logout"
              @click="handleLogout"
            >
              {{ t('common.sign_out') || 'Sign Out' }}
            </v-btn>
          </div>
        </v-card-text>
      </v-card>
    </v-menu>

    <!-- Preferences Dialog -->
    <PreferencesDialog v-model="prefDialog" />

    <!-- Cash Register panel -->
    <AppDialog
      v-model="registerDialog"
      :title="t('cash_register.panel_title')"
      :max-width="380"
      :loading="registerBusy"
    >
      <div v-if="registerOpen && !closing">
        <div class="d-flex justify-space-between text-body-2 mb-2">
          <span class="text-medium-emphasis">{{ t('cash_register.opening_float') }}</span>
          <span class="font-weight-bold">{{ formatMoney(currentDrawer?.opening_float) }}</span>
        </div>
        <div class="d-flex justify-space-between text-body-2 mb-4">
          <span class="text-medium-emphasis">{{ t('cash_register.opened_at') }}</span>
          <span class="font-weight-bold">{{ formatDateTime(currentDrawer?.opened_at) }}</span>
        </div>
        <v-btn
          color="error"
          variant="tonal"
          block
          rounded="lg"
          prepend-icon="mdi-lock-outline"
          @click="closing = true"
        >
          {{ t('cash_register.close_register') }}
        </v-btn>
      </div>

      <div v-else-if="registerOpen && closing">
        <v-text-field
          v-model.number="actualCash"
          type="number"
          min="0"
          step="0.01"
          :label="t('cash_register.actual_cash')"
          variant="outlined"
          rounded="lg"
        />
        <v-textarea
          v-model="closeNotes"
          :label="t('cash_register.notes_optional')"
          variant="outlined"
          rounded="lg"
          rows="2"
        />
        <div class="d-flex gap-2">
          <v-btn variant="tonal" rounded="lg" class="flex-1-1" @click="closing = false">
            {{ t('btn.cancel') }}
          </v-btn>
          <v-btn
            color="error"
            variant="flat"
            rounded="lg"
            class="flex-1-1"
            :disabled="actualCash === null || actualCash === ''"
            @click="confirmClose"
          >
            {{ t('cash_register.confirm_close') }}
          </v-btn>
        </div>
      </div>

      <div v-else>
        <p class="text-body-2 text-medium-emphasis mb-3">{{ t('cash_register.open_prompt') }}</p>
        <v-text-field
          v-model.number="openingFloat"
          type="number"
          min="0"
          step="0.01"
          :label="t('cash_register.opening_float')"
          variant="outlined"
          rounded="lg"
        />
        <v-btn
          color="success"
          variant="flat"
          block
          rounded="lg"
          prepend-icon="mdi-lock-open-variant-outline"
          @click="confirmOpen"
        >
          {{ t('cash_register.open_register') }}
        </v-btn>
      </div>
    </AppDialog>

    <!-- Keyboard Shortcuts panel -->
    <AppDialog
      v-model="shortcutsDialog"
      :title="t('shortcuts.title')"
      :max-width="360"
      :persistent="false"
    >
      <div v-for="row in shortcutRows" :key="row.keys" class="d-flex justify-space-between align-center mb-3">
        <span class="text-body-2">{{ row.label }}</span>
        <kbd class="shortcut-key">{{ row.keys }}</kbd>
      </div>

      <template #actions>
        <v-btn variant="tonal" rounded="lg" @click="shortcutsDialog = false">
          {{ t('btn.close') }}
        </v-btn>
      </template>
    </AppDialog>
  </div>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useAuthStore } from '@/stores/authStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useRouter } from 'vue-router'
  import { useI18n } from 'vue-i18n'
  import PreferencesDialog from '../common/PreferencesDialog.vue'
  import LanguageSwitch from '../common/LanguageSwitch.vue'
  import { useAvatar } from '@/composables/useAvatar'
  import { useOnlineStatus } from '@/composables/useOnlineStatus'
  import { useNotificationBell } from '@/composables/useNotificationBell'
  import { useLogout } from '@/composables/useLogout'
  import { useCashDrawerStore } from '@/stores/cashDrawerStore'
  import { useAppUtils, AppNotificationBell, AppDialog } from '@nong-official-dev/core'

  const { t } = useI18n()
  const { notif } = useAppUtils()
  const { getInitials, getAvatarColor } = useAvatar()
  const { isOnline } = useOnlineStatus()
  const bell = useNotificationBell()
  const { logout } = useLogout()

  const authStore = useAuthStore()
  const branchStore = useBranchStore()
  const cashDrawerStore = useCashDrawerStore()
  const router = useRouter()

  const props = defineProps({
    user: Object,
    buName: String,
    branchName: String,
    roleName: String
  })

  // State
  const userMenu = ref(false)
  const prefDialog = ref(false)

  const openPreferences = () => {
    userMenu.value = false
    prefDialog.value = true
  }

  // ── Cash register ──────────────────────────────────────────────────────
  const registerOpen = ref(false)
  const currentDrawer = ref(null)
  const registerDialog = ref(false)
  const registerBusy = ref(false)
  const closing = ref(false)
  const actualCash = ref(null)
  const closeNotes = ref('')
  const openingFloat = ref(0)

  async function loadRegisterState() {
    if (!authStore.staff_id) return
    await cashDrawerStore.fetchCashDrawers({
      staff_id: authStore.staff_id,
      is_open: true,
      perPage: 1
    })
    const drawer = cashDrawerStore.cashDrawers[0] ?? null
    currentDrawer.value = drawer
    registerOpen.value = !!drawer
  }

  function openRegisterPanel() {
    closing.value = false
    actualCash.value = null
    closeNotes.value = ''
    openingFloat.value = 0
    registerDialog.value = true
  }

  async function confirmOpen() {
    registerBusy.value = true
    try {
      await cashDrawerStore.openCashDrawer({
        branch_id: authStore.branch_id,
        staff_id: authStore.staff_id,
        opening_float: openingFloat.value || 0
      })
      await loadRegisterState()
      notif(t('cash_register.opened_success'), { type: 'success' })
      registerDialog.value = false
    } catch {
      notif(t('cash_register.action_failed'), { type: 'error' })
    } finally {
      registerBusy.value = false
    }
  }

  async function confirmClose() {
    if (!currentDrawer.value) return
    registerBusy.value = true
    try {
      await cashDrawerStore.closeCashDrawer(currentDrawer.value.id, {
        actual_cash: actualCash.value,
        notes: closeNotes.value || undefined
      })
      await loadRegisterState()
      notif(t('cash_register.closed_success'), { type: 'success' })
      registerDialog.value = false
    } catch {
      notif(t('cash_register.action_failed'), { type: 'error' })
    } finally {
      registerBusy.value = false
    }
  }

  function formatMoney(value) {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(value ?? 0)
  }

  // ── Keyboard shortcuts ─────────────────────────────────────────────────
  const shortcutsDialog = ref(false)
  const shortcutRows = computed(() => [
    { keys: '⌘/Ctrl K · F2', label: t('shortcuts.search') },
    { keys: 'F10', label: t('shortcuts.checkout') },
    { keys: 'Esc', label: t('shortcuts.close') }
  ])

  function openShortcuts() {
    userMenu.value = false
    shortcutsDialog.value = true
  }

  // Enabled per-branch (Settings > Customer Display), not tenant-wide — see
  // ShopBranchSwitcher.vue, which fetches branchStore.branches (incl.
  // customer_display_settings) for every role, staff included.
  const customerDisplayEnabled = computed(
    () =>
      branchStore.branches.find(b => b.id === authStore.activeBranchId)
        ?.customer_display_settings?.enabled === true
  )

  function openCustomerDisplay() {
    userMenu.value = false
    window.open('/customer-display', '_blank', 'noopener')
  }

  // Personal account actions only — Security and Billing are business/
  // system-level and live in the sidebar (System group) instead, so the
  // same destination isn't reachable from two differently-labeled menus.
  const menuItems = computed(() => [
    {
      title: 'profile.title',
      icon: 'mdi-account-circle-outline',
      action: () => goToProfile()
    },
    {
      title: 'app_bar.appearance',
      icon: 'mdi-tune-variant',
      action: () => openPreferences()
    },
    ...(customerDisplayEnabled.value
      ? [
          {
            title: 'settings_hub.customer_display.open_display',
            icon: 'mdi-monitor-share',
            action: () => openCustomerDisplay()
          }
        ]
      : []),
    {
      title: 'shortcuts.title',
      icon: 'mdi-keyboard-outline',
      action: () => openShortcuts()
    }
  ])

  // Avatar
  const initials = computed(() => getInitials(props.user, '?'))

  const avatarColor = computed(() => {
    const colors = [
      'primary',
      'secondary',
      'success',
      'warning',
      'error',
      'info',
      'teal',
      'purple'
    ]
    return getAvatarColor(props.user, { palette: colors })
  })

  onMounted(() => {
    loadRegisterState()
  })

  // Navigation
  const goToProfile = () => {
    userMenu.value = false
    router.push({ name: 'profile' })
  }

  // Logout
  const handleLogout = () => {
    userMenu.value = false
    logout()
  }
</script>

<style scoped>
  .app-bar-actions {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .gap-1 {
    gap: 4px;
  }
  .gap-2 {
    gap: 8px;
  }
  .gap-3 {
    gap: 12px;
  }
  .min-w-0 {
    min-width: 0;
  }

  .user-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 4px 8px 4px 4px;
    border-radius: 999px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-family: inherit;
    transition: background-color 0.15s;
  }
  .user-btn:hover {
    background: rgba(var(--v-theme-on-surface), 0.06);
  }

  .avatar-ring {
    outline: 2px solid rgba(var(--v-theme-primary), 0.3);
    outline-offset: 2px;
    transition: outline-color 0.2s;
  }
  .avatar-ring:hover {
    outline-color: rgba(var(--v-theme-primary), 0.7);
  }

  .shortcut-key {
    font-family: inherit;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 6px;
    background: rgba(var(--v-theme-on-surface), 0.08);
    border: 1px solid rgba(var(--v-theme-on-surface), 0.14);
  }
</style>
