<template>
  <div>
    <template v-if="layout === 'classic' || layout === 'icon_rail'">
      <sidebar
        :variant="layout"
        v-model="navOpen"
        :user="user"
        :bu-name="buName"
        :branch-name="branchName"
        :role-name="roleName"
      />
      <app-bar
        :user="user"
        @toggle="navOpen = true"
        :bu-name="buName"
        :branch-name="branchName"
        :role-name="roleName"
      />
    </template>

    <sidebar
      v-else-if="layout === 'sidebar_only'"
      variant="sidebar_only"
      v-model="navOpen"
      :user="user"
      :bu-name="buName"
      :branch-name="branchName"
      :role-name="roleName"
    />

    <template v-else-if="layout === 'pos_focus'">
      <sidebar
        variant="pos_focus"
        v-model="navOpen"
        :user="user"
        :bu-name="buName"
        :branch-name="branchName"
        :role-name="roleName"
      />
      <app-bar
        :user="user"
        :force-hamburger="true"
        @toggle="navOpen = true"
        :bu-name="buName"
        :branch-name="branchName"
        :role-name="roleName"
      />
    </template>

    <!-- Mobile open trigger for Sidebar-only, the one other layout with no
         AppBar hamburger -->
    <v-btn
      v-if="layout === 'sidebar_only' && mdAndDown"
      icon="mdi-menu"
      class="mobile-nav-fab"
      color="primary"
      elevation="4"
      size="large"
      @click="navOpen = true"
    />

    <!-- Unified collapse/expand handle — desktop only, permanent layouts only -->
    <SidebarCollapseHandle
      v-if="showCollapseHandle"
      :collapsed="isCollapsed"
      :left="handleLeft"
      @toggle="toggleCollapse"
    />

    <v-main>
      <v-container class="px-4" fluid>
        <v-alert
          v-if="blockedApiError"
          type="error"
          variant="tonal"
          density="comfortable"
          rounded="lg"
          class="mb-4"
        >
          {{ blockedMessage }}
          <template #append>
            <v-btn
              size="small"
              variant="text"
              color="warning"
              @click="$router.push('/tenants-billing')"
            >
              {{ $t('subscription.blocked_banner.action') }}
            </v-btn>
          </template>
        </v-alert>

        <v-alert
          v-else-if="trialDaysLeft !== null"
          type="warning"
          variant="tonal"
          density="comfortable"
          rounded="0"
          border="start"
          class="mb-4"
          closable
        >
          {{
            $t('subscription.trial_banner.message', { count: trialDaysLeft })
          }}
          <template #append>
            <v-btn
              size="small"
              variant="text"
              color="warning"
              @click="$router.push('/tenants-billing')"
            >
              {{ $t('subscription.trial_banner.action') }}
            </v-btn>
          </template>
        </v-alert>

        <router-view v-slot="{ Component, route }">
          <transition :name="route.meta.transitionName || 'fade'" mode="out-in">
            <component :is="Component" :key="route.path" />
          </transition>
        </router-view>
      </v-container>
    </v-main>
  </div>
</template>

<script setup>
  import { ref, computed, onMounted, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import Sidebar from './Sidebar.vue'
  import AppBar from './AppBar.vue'
  import SidebarCollapseHandle from './SidebarCollapseHandle.vue'
  import { useAuthStore } from '@/stores/authStore'
  import { useUiPreferencesStore } from '@/stores/uiPreferencesStore'
  import { useSidebarCollapse } from '@/composables/useSidebarCollapse'
  import { SIDEBAR_RAIL_WIDTH, SIDEBAR_EXPANDED_WIDTH } from '@/config/sidebarLayout'
  import { useRouter } from 'vue-router'

  const authStore = useAuthStore()
  const uiPrefs = useUiPreferencesStore()
  const router = useRouter()
  const { t, te } = useI18n()

  const layout = computed(() => uiPrefs.layout)

  // Single open/close state for whichever Sidebar instance is currently
  // rendered — temporary overlay below lg (every layout) and always for
  // POS-focus; a no-op v-model for the permanent desktop drawer.
  const navOpen = ref(false)

  const { isTemporary, isCollapsed, toggleCollapse, mdAndDown } = useSidebarCollapse(layout)
  // The handle only makes sense for a permanent desktop drawer — hidden on
  // POS-focus (always temporary) and below lg (temporary overlay, opened by
  // the hamburger/fab instead).
  const showCollapseHandle = computed(() => !isTemporary.value)
  const handleLeft = computed(
    () => (isCollapsed.value ? SIDEBAR_RAIL_WIDTH : SIDEBAR_EXPANDED_WIDTH) - 12
  )

  // Resizing back up to desktop mid-overlay shouldn't leave it primed to
  // reopen immediately next time the viewport narrows again.
  watch(isTemporary, v => {
    if (!v) navOpen.value = false
  })

  const user = computed(() => authStore.me)
  const buName = computed(() => authStore.bu_name)
  const branchName = computed(() => authStore.branch_name)
  const roleName = computed(() => authStore.role_name)
  const trialDaysLeft = computed(() => authStore.trialDaysLeft)
  const blockedApiError = computed(() => authStore.blockedApiError)
  // Translated-by-code when we have a mapping for it; otherwise fall back
  // to the backend's own message rather than showing nothing.
  const blockedMessage = computed(() => {
    const key = `apiErrors.${blockedApiError.value?.code}`
    return te(key) ? t(key) : blockedApiError.value?.message
  })

  onMounted(() => {
    if (!authStore.me?.id) {
      authStore.logout()
      router.push({ name: 'Login' })
    }
  })
</script>

<style>
  /* Sidebar-only on mdAndDown — floating nav trigger, no AppBar there */
  .mobile-nav-fab {
    position: fixed;
    top: 16px;
    left: 16px;
    z-index: 1005;
  }

  /* Forward → */
  .slide-enter-from {
    opacity: 0;
    transform: translateX(24px);
  }
  .slide-leave-to {
    opacity: 0;
    transform: translateX(-24px);
  }

  /* Back ← */
  .slide-right-enter-from {
    opacity: 0;
    transform: translateX(-24px);
  }
  .slide-right-leave-to {
    opacity: 0;
    transform: translateX(24px);
  }

  .slide-enter-active,
  .slide-leave-active,
  .slide-right-enter-active,
  .slide-right-leave-active {
    transition: all 0.2s ease;
  }

  .fade-enter-active,
  .fade-leave-active {
    transition: opacity 0.15s ease;
  }
  .fade-enter-from,
  .fade-leave-to {
    opacity: 0;
  }

  /* Sidebar styles */
  .v-navigation-drawer__content {
    height: 100%;
    overflow-y: auto;
    overflow-x: hidden;
    -ms-overflow-style: none;
    scrollbar-width: none;
  }
  .v-navigation-drawer__content::-webkit-scrollbar {
    display: none;
  }
  .v-list-item__append {
    display: initial !important;
    align-items: unset !important;
  }
</style>
