<script setup>
  import { ref, computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import FooterNotificationsMenu from './FooterNotificationsMenu.vue'
  import { useNotificationBell } from '@/composables/useNotificationBell'
  import { useUserMenu } from '@/composables/useUserMenu'
  import { useLanguage } from '@/composables/useLanguage'
  import { useAvatar } from '@/composables/useAvatar'
  import { useOnlineStatus } from '@/composables/useOnlineStatus'
  import { useLogout } from '@/composables/useLogout'

  const props = defineProps({
    user: Object,
    buName: String,
    branchName: String,
    roleName: String,
    rail: Boolean
  })

  const { t } = useI18n()
  const { getInitials, getAvatarColor } = useAvatar()
  const { isOnline } = useOnlineStatus()
  const bell = useNotificationBell()
  const { goToAccount, customerDisplayEnabled, openCustomerDisplay } = useUserMenu()
  const { options, currentLanguage, select } = useLanguage()
  const { logout } = useLogout()

  const langMenu = ref(false)

  function onSelectLanguage(code) {
    langMenu.value = false
    select(code)
  }

  const initials = computed(() => getInitials(props.user, '?'))
  const avatarColor = computed(() => getAvatarColor(props.user))
  const shortCode = computed(() => currentLanguage.value.code.toUpperCase())

  // Role·Branch when there's one to show (an owner has no single branch —
  // "Owner" alone still shows); falls back to email so the second line is
  // never just blank for whatever role/branch combination is missing.
  const roleAndBranch = computed(() => [props.roleName, props.branchName].filter(Boolean).join(' · '))
  const metaText = computed(() => roleAndBranch.value || props.user?.email || '')
</script>

<template>
  <div class="footer">
    <div class="card">
      <!-- ── Expanded ──────────────────────────────────────────────────── -->
      <template v-if="!rail">
        <div class="top">
          <button type="button" class="identity" :aria-label="t('account.title')" @click="goToAccount('profile')">
            <span class="avatar" :class="`bg-${avatarColor}`">
              <img v-if="user?.avatar_url" :src="user.avatar_url" alt="" class="avatar-img" />
              <span v-else class="avatar-initials">{{ initials }}</span>
              <span class="avatar-dot" :class="isOnline ? 'avatar-dot--on' : 'avatar-dot--off'" />
            </span>
            <span class="identity-text">
              <span class="name">{{ user?.first_name }} {{ user?.last_name }}</span>
              <span v-if="metaText" class="meta">{{ metaText }}</span>
            </span>
          </button>

          <button
            v-if="customerDisplayEnabled"
            type="button"
            class="icon-btn"
            :aria-label="t('settings_hub.customer_display.open_display')"
            @click="openCustomerDisplay"
          >
            <v-icon icon="mdi-monitor-share" size="18" />
          </button>

          <button
            type="button"
            class="icon-btn"
            :aria-label="t('logout.aria_label')"
            @click="logout"
          >
            <v-icon icon="mdi-logout" size="18" />
          </button>
        </div>

        <div class="bottom">
          <v-menu v-model="langMenu" location="bottom end" offset="8">
            <template #activator="{ props: menuProps }">
              <button type="button" v-bind="menuProps" class="lang-btn" :aria-label="t('lang.change')">
                <img :src="currentLanguage.flag" width="20" height="14" class="flag" alt="" />
                <span>{{ shortCode }}</span>
                <v-icon icon="mdi-chevron-down" size="14" />
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
                <v-list-item-title class="text-body-2">{{ opt.label }}</v-list-item-title>
              </v-list-item>
            </v-list>
          </v-menu>

          <span class="divider" />

          <FooterNotificationsMenu
            v-bind="bell.bellProps"
            :unread-count="bell.unreadCount"
            :label="t('app_bar.alerts')"
            @item-click="bell.goToNotifications"
            @view-all="bell.goToNotifications"
            @read="bell.refreshUnreadCount"
          />
        </div>
      </template>

      <!-- ── Rail ──────────────────────────────────────────────────────── -->
      <template v-else>
        <div class="rail">
          <v-menu v-model="langMenu" location="end" offset="8">
            <template #activator="{ props: menuProps }">
              <v-tooltip location="end" :text="currentLanguage.label">
                <template #activator="{ props: tip }">
                  <button type="button" v-bind="{ ...tip, ...menuProps }" class="rail-btn" :aria-label="t('lang.change')">
                    <span>{{ shortCode }}</span>
                  </button>
                </template>
              </v-tooltip>
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
                <v-list-item-title class="text-body-2">{{ opt.label }}</v-list-item-title>
              </v-list-item>
            </v-list>
          </v-menu>

          <FooterNotificationsMenu
            v-bind="bell.bellProps"
            rail
            :unread-count="bell.unreadCount"
            @item-click="bell.goToNotifications"
            @view-all="bell.goToNotifications"
            @read="bell.refreshUnreadCount"
          />

          <span class="rail-divider" />

          <v-tooltip location="end" :text="t('account.title')">
            <template #activator="{ props: tip }">
              <button type="button" v-bind="tip" class="rail-avatar" :aria-label="t('account.title')" @click="goToAccount('profile')">
                <span class="avatar" :class="`bg-${avatarColor}`">
                  <img v-if="user?.avatar_url" :src="user.avatar_url" alt="" class="avatar-img" />
                  <span v-else class="avatar-initials">{{ initials }}</span>
                  <span class="avatar-dot" :class="isOnline ? 'avatar-dot--on' : 'avatar-dot--off'" />
                </span>
              </button>
            </template>
          </v-tooltip>

          <v-tooltip location="end" :text="t('logout.aria_label')">
            <template #activator="{ props: tip }">
              <button type="button" v-bind="tip" class="rail-btn" :aria-label="t('logout.aria_label')" @click="logout">
                <v-icon icon="mdi-logout" size="18" />
              </button>
            </template>
          </v-tooltip>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
  .footer {
    padding: 8px;
  }

  .card {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 14px;
    background: rgb(var(--v-theme-surface));
    overflow: hidden;
  }

  /* ── Top row ─────────────────────────────────────────────────────────── */
  .top {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 8px 10px 10px;
  }

  .identity {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    border: 0;
    background: transparent;
    cursor: pointer;
    padding: 2px;
    border-radius: 10px;
    font-family: inherit;
    text-align: start;
  }
  .identity:hover {
    background: rgba(var(--v-theme-on-surface), 0.06);
  }
  .identity:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }

  .avatar {
    width: 38px;
    height: 38px;
    border-radius: 999px;
    position: relative;
    flex: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    overflow: visible;
  }
  .avatar-img {
    width: 100%;
    height: 100%;
    border-radius: 999px;
    object-fit: cover;
  }
  .avatar-initials {
    font-size: 13px;
    font-weight: 700;
    color: #fff;
  }
  .avatar-dot {
    position: absolute;
    right: -1px;
    bottom: -1px;
    width: 11px;
    height: 11px;
    border-radius: 50%;
    border: 2px solid rgb(var(--v-theme-surface));
  }
  .avatar-dot--on {
    background: rgb(var(--v-theme-success));
  }
  .avatar-dot--off {
    background: rgb(var(--v-theme-error));
  }

  .identity-text {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    line-height: 1.25;
  }
  .name {
    font-size: 14px;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .meta {
    font-size: 12px;
    color: rgba(var(--v-theme-on-surface), 0.6);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .icon-btn {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: 0;
    background: transparent;
    color: rgba(var(--v-theme-on-surface), 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    flex: none;
    cursor: pointer;
  }
  .icon-btn:hover {
    background: rgba(var(--v-theme-on-surface), 0.06);
  }
  .icon-btn:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }

  /* ── Bottom row ──────────────────────────────────────────────────────── */
  .bottom {
    display: grid;
    grid-template-columns: 1fr 1px 1fr;
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }
  .bottom :deep(button),
  .lang-btn {
    min-height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font: 600 13px inherit;
    border: 0;
    background: transparent;
    width: 100%;
    color: rgb(var(--v-theme-on-surface));
    cursor: pointer;
  }
  .lang-btn:hover,
  .bottom :deep(button):hover {
    background: rgba(var(--v-theme-on-surface), 0.06);
  }
  .lang-btn:focus-visible,
  .bottom :deep(button):focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: -2px;
  }

  .divider {
    background: rgba(var(--v-border-color), var(--v-border-opacity));
  }

  .flag {
    border-radius: 2px;
    flex-shrink: 0;
    object-fit: cover;
  }

  .badge {
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: #d92d20;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
  }

  /* ── Rail ────────────────────────────────────────────────────────────── */
  .rail {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 10px 6px;
  }
  .rail-btn {
    width: 44px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    border: 0;
    background: transparent;
    color: rgb(var(--v-theme-on-surface));
    font: 600 13px inherit;
    cursor: pointer;
  }
  .rail-btn:hover {
    background: rgba(var(--v-theme-on-surface), 0.06);
  }
  .rail-btn:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }
  .rail-divider {
    width: 32px;
    height: 1px;
    background: rgba(var(--v-border-color), var(--v-border-opacity));
  }
  .rail-avatar {
    border: 0;
    background: transparent;
    padding: 0;
    border-radius: 999px;
    cursor: pointer;
  }
  .rail-avatar:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }
</style>
