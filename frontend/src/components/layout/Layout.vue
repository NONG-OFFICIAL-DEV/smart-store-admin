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
  import Sidebar from './Sidebar.vue'
  import AppBar from './AppBar.vue'
  import { useAuthStore } from '@/stores/authStore'
  import { useRouter } from 'vue-router'

  const rail = ref(localStorage.getItem('sidebar-rail') === 'true')
  const authStore = useAuthStore()
  const router = useRouter()

  const user = computed(() => authStore.me)
  const buName = computed(() => authStore.bu_name)
  const branchName = computed(() => authStore.branch_name)
  const roleName = computed(() => authStore.role_name)
  const logo_url = computed(() => authStore.logo_url)

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
