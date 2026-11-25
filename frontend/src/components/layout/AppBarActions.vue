<template>
  <div class="app-bar-actions">
    <!-- Offline pill — left of everything else in this row -->
    <div v-if="!isOnline" class="offline-pill" :class="{ 'offline-pill--dark': isDark }">
      <span class="offline-dot" />
      {{ t('app_bar.offline_message') }}
    </div>

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

    <!-- 1. Language -->
    <v-menu v-model="langMenu" location="bottom end" offset="8">
      <template #activator="{ props: menuProps }">
        <button type="button" class="bar-btn lang-btn" v-bind="menuProps" :aria-label="t('lang.change')">
          <img :src="currentLanguage.flag" width="22" height="16" class="lang-flag" alt="" />
          <span class="lang-code d-none d-md-inline">{{ shortCode }}</span>
          <v-icon icon="mdi-chevron-down" size="14" class="bar-chevron" />
        </button>
      </template>

      <v-list class="pa-1" rounded="lg" density="compact">
        <v-list-item
          v-for="opt in options"
          :key="opt.code"
          :active="currentLanguage.code === opt.code"
          rounded="md"
          @click="onSelectLanguage(opt.code)"
        >
          <template #prepend>
            <img :src="opt.flag" width="20" height="14" class="flag me-3" alt="" />
          </template>
          <v-list-item-title class="text-body-medium">{{ opt.label }}</v-list-item-title>
        </v-list-item>
      </v-list>
    </v-menu>

    <!-- 2. Notifications -->
    <FooterNotificationsMenu
      :ref="bell.bellRef"
      bar
      v-bind="bell.bellProps"
      :unread-count="bell.unreadCount.value"
      @item-click="bell.goToNotifications"
      @view-all="bell.goToNotifications"
      @read="bell.refreshUnreadCount"
    />

    <!-- 3. Divider -->
    <span class="bar-divider" />

    <!-- 4. Account -->
    <v-menu v-model="userMenu" rounded="xl" min-width="230" offset="8" :close-on-content-click="false">
      <template #activator="{ props: menuProps }">
        <button type="button" class="bar-btn account-btn" v-bind="menuProps" :aria-label="t('app_bar.account_menu')">
          <span class="account-avatar" :class="`bg-${avatarColor}`">
            <img v-if="user?.avatar_url" :src="user.avatar_url" alt="" class="account-avatar-img" />
            <span v-else class="account-initials">{{ initials }}</span>
            <span class="account-dot" :class="isOnline ? 'account-dot--on' : 'account-dot--off'" />
          </span>
          <span class="account-text d-none d-md-flex">
            <span class="account-name">{{ user?.first_name }} {{ user?.last_name }}</span>
            <span v-if="roleName" class="account-role">{{ roleName }}</span>
          </span>
          <v-icon icon="mdi-unfold-more-horizontal" size="18" class="bar-chevron d-none d-md-inline-flex" />
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
        <div class="d-flex justify-space-between text-body-medium mb-2">
          <span class="text-medium-emphasis">{{ t('cash_register.opening_float') }}</span>
          <span class="font-weight-bold">{{ formatMoney(currentDrawer?.opening_float) }}</span>
        </div>
        <div class="d-flex justify-space-between text-body-medium mb-4">
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
        <p class="text-body-medium text-medium-emphasis mb-3">{{ t('cash_register.open_prompt') }}</p>
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
  import { ref, computed, watch, onMounted } from 'vue'
  import { useTheme } from 'vuetify'
  import { useAuthStore } from '@/stores/authStore'
  import { useI18n } from 'vue-i18n'
  import FooterNotificationsMenu from './FooterNotificationsMenu.vue'
  import UserMenuCard from './UserMenuCard.vue'
  import { useAvatar } from '@/composables/useAvatar'
  import { useOnlineStatus } from '@/composables/useOnlineStatus'
  import { useNotificationBell } from '@/composables/useNotificationBell'
  import { useUserMenu } from '@/composables/useUserMenu'
  import { useLanguage } from '@/composables/useLanguage'
  import { useLogout } from '@/composables/useLogout'
  import { useCashDrawerStore } from '@/stores/cashDrawerStore'
  import { useAppUtils, AppDialog } from '@nong-official-dev/core'

  const { t } = useI18n()
  const { notif } = useAppUtils()
  const theme = useTheme()
  const { getInitials, getAvatarColor } = useAvatar()
  const { isOnline } = useOnlineStatus()
  const bell = useNotificationBell()
  const { goToAccount } = useUserMenu()
  const { options, currentLanguage, select } = useLanguage()
  const { logout } = useLogout()

  const authStore = useAuthStore()
  const cashDrawerStore = useCashDrawerStore()

  const props = defineProps({
    user: Object,
    buName: String,
    branchName: String,
    roleName: String
  })

  const isDark = computed(() => theme.global.current.value.dark)

  const userMenu = ref(false)
  const langMenu = ref(false)
  const shortCode = computed(() => currentLanguage.value.code.toUpperCase())

  function onSelectLanguage(code) {
    langMenu.value = false
    select(code)
  }

  function onProfileClick() {
    userMenu.value = false
    goToAccount('profile')
  }

  // Back-online is worth a one-off confirmation; the offline pill already
  // covers "you're offline" for as long as that lasts, so no snackbar there.
  watch(isOnline, (value, wasOnline) => {
    if (value && wasOnline === false) {
      notif(t('app_bar.back_online'), { type: 'success' })
    }
  })

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
    gap: 6px;
  }

  /* ── Shared control chrome ──────────────────────────────────────────── */
  .bar-btn {
    height: 40px;
    min-width: 40px;
    padding: 0 10px;
    display: flex;
    align-items: center;
    gap: 6px;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: rgb(var(--v-theme-on-surface));
    cursor: pointer;
    font-family: inherit;
  }
  .bar-btn:hover {
    background: rgba(var(--v-theme-on-surface), 0.06);
  }
  .bar-btn:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }
  .bar-chevron {
    flex: none;
    opacity: 0.55;
  }
  .bar-divider {
    width: 1px;
    height: 28px;
    flex: none;
    margin: 0 6px;
    background: rgba(var(--v-border-color), var(--v-border-opacity));
  }

  /* ── Language ──────────────────────────────────────────────────────── */
  .lang-flag {
    flex: none;
    border-radius: 4px;
    object-fit: cover;
    display: block;
  }
  .lang-code {
    font-size: 13px;
    font-weight: 600;
  }

  /* ── Account ───────────────────────────────────────────────────────── */
  /* Same chrome as the branch switcher trigger (ShopSwitcher.vue): bordered,
     12px radius, surface background, 52px tall. */
  .account-btn {
    gap: 10px;
    min-height: 52px;
    height: auto;
    padding: 8px 10px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 12px;
    background: rgb(var(--v-theme-surface));
    text-align: start;
  }
  .account-btn:hover {
    background: rgba(var(--v-theme-on-surface), 0.04);
  }
  .account-avatar {
    position: relative;
    width: 34px;
    height: 34px;
    border-radius: 999px;
    flex: none;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: visible;
  }
  .account-avatar-img {
    width: 100%;
    height: 100%;
    border-radius: 999px;
    object-fit: cover;
  }
  .account-initials {
    font-size: 13px;
    font-weight: 700;
    color: #fff;
  }
  .account-dot {
    position: absolute;
    right: -1px;
    bottom: -1px;
    width: 11px;
    height: 11px;
    border-radius: 50%;
    border: 2px solid rgb(var(--v-theme-surface));
  }
  .account-dot--on {
    background: rgb(var(--v-theme-success));
  }
  .account-dot--off {
    background: rgb(var(--v-theme-error));
  }
  .account-text {
    flex-direction: column;
    line-height: 1.25;
    text-align: start;
    min-width: 0;
  }
  .account-name {
    font-size: 14px;
    font-weight: 700;
    max-width: 140px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .account-role {
    font-size: 12px;
    color: rgba(var(--v-theme-on-surface), 0.6);
    max-width: 140px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  /* ── Offline pill ──────────────────────────────────────────────────── */
  .offline-pill {
    display: flex;
    align-items: center;
    gap: 6px;
    height: 32px;
    padding: 0 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    margin-right: auto;
    background: #fef3f2;
    color: #b42318;
  }
  .offline-pill--dark {
    background: rgba(240, 68, 56, 0.15);
    color: #fda29b;
  }
  .offline-dot {
    width: 6px;
    height: 6px;
    flex: none;
    border-radius: 50%;
    background: #f04438;
  }
</style>