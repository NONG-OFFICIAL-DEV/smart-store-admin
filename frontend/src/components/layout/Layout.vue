<template>
  <div>
    <app-bar
      :user="user"
      :rail="rail"
      @toggle="toggleRail"
      :bu-name="buName"
      :branch-name="branchName"
      :role-name="roleName"
      :logo-url="logo_url"
    />
    <sidebar :user="user" v-model:rail="rail" />
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
  import { useAuthStore } from '@/stores/authStore'
  import { useRouter } from 'vue-router'

  const rail = ref(localStorage.getItem('sidebar-rail') === 'true')
  const authStore = useAuthStore()
  const router = useRouter()
  const { t, te } = useI18n()

  const user = computed(() => authStore.me)
  const buName = computed(() => authStore.bu_name)
  const branchName = computed(() => authStore.branch_name)
  const roleName = computed(() => authStore.role_name)
  const logo_url = computed(() => authStore.logo_url)
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

  watch(rail, val => {
    localStorage.setItem('sidebar-rail', val)
  })

  function toggleRail() {
    rail.value = !rail.value
  }
</script>

<style>
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
