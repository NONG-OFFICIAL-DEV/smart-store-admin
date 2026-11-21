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

    <!-- Customer display -->
    <v-btn
      v-if="customerDisplayEnabled"
      icon
      variant="text"
      size="small"
      :aria-label="t('settings_hub.customer_display.open_display')"
      @click="openCustomerDisplay"
    >
      <v-icon icon="mdi-monitor-share" size="20" />
    </v-btn>

    <!-- Avatar / user menu (Profile + Sign out) -->
    <v-menu v-model="userMenu" rounded="xl" min-width="230" offset="8" :close-on-content-click="false">
      <template #activator="{ props: menuProps }">
        <button type="button" class="user-btn" v-bind="menuProps" :aria-label="t('app_bar.account_menu')">
          <v-avatar :color="avatarColor" size="36" class="avatar-ring">
            <v-img v-if="user?.avatar_url" :src="user.avatar_url" cover />
            <span v-else class="text-caption font-weight-black text-white">
              {{ initials }}
            </span>
          </v-avatar>
        </button>
      </template>

      <UserMenuCard
        :user="user"
        :initials="initials"
        :avatar-color="avatarColor"
        @profile-click="onProfileClick"
        @logout="handleLogout"
      />
    </v-menu>

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
  </div>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useAuthStore } from '@/stores/authStore'
  import { useI18n } from 'vue-i18n'
  import LanguageSwitch from '../common/LanguageSwitch.vue'
  import UserMenuCard from './UserMenuCard.vue'
  import { useAvatar } from '@/composables/useAvatar'
  import { useOnlineStatus } from '@/composables/useOnlineStatus'
  import { useNotificationBell } from '@/composables/useNotificationBell'
  import { useUserMenu } from '@/composables/useUserMenu'
  import { useLogout } from '@/composables/useLogout'
  import { useCashDrawerStore } from '@/stores/cashDrawerStore'
  import { useAppUtils, AppNotificationBell, AppDialog } from '@nong-official-dev/core'

  const { t } = useI18n()
  const { notif } = useAppUtils()
  const { getInitials, getAvatarColor } = useAvatar()
  const { isOnline } = useOnlineStatus()
  const bell = useNotificationBell()
  const { goToAccount, customerDisplayEnabled, openCustomerDisplay } = useUserMenu()
  const { logout } = useLogout()

  const authStore = useAuthStore()
  const cashDrawerStore = useCashDrawerStore()

  const props = defineProps({
    user: Object,
    buName: String,
    branchName: String,
    roleName: String
  })

  const userMenu = ref(false)

  function onProfileClick() {
    userMenu.value = false
    goToAccount('profile')
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
