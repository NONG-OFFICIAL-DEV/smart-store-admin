<template>
  <sidebar :user="user" v-model:rail="rail" :logo_url="logo_url" />
  <app-bar
    :user="user"
    @toggle="toggleRail"
    :notifications_count="notifications_count"
    :bu_name="bu_name"
  />
  <v-main>
    <v-container class="px-4" fluid>
      <router-view />
    </v-container>
  </v-main>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import Sidebar from './Sidebar.vue'
  import AppBar from './AppBar.vue'
  import { useAuthStore } from '@/stores/authStore'
  import { useRouter } from 'vue-router'

  const rail = ref(false)
  const authStore = useAuthStore()
  const router = useRouter()

  // ✅ Just read from store — router guard already called fetchMe
  const user = computed(() => authStore.me)
  const notifications_count = computed(
    () => authStore.unread_notifications_count
  )
  const bu_name = computed(() => authStore.bu_name)
  const logo_url = computed(() => authStore.logo_url)

  // Only keep logout fallback if needed
  onMounted(() => {
    if (!authStore.me?.id) {
      authStore.logout()
      router.push({ name: 'Login' })
    }
  })

  function toggleRail() {
    rail.value = !rail.value
  }
</script>
<style>
  .v-navigation-drawer__content {
    height: 100%;
    overflow-y: auto;
    overflow-x: hidden;
  }
  /* Hide scrollbar for Webkit browsers */
  .v-navigation-drawer__content::-webkit-scrollbar {
    display: none;
  }

  /* Hide scrollbar for Firefox */
  .v-navigation-drawer__content {
    -ms-overflow-style: none; /* IE and Edge */
    scrollbar-width: none; /* Firefox */
  }

  .v-list-item__append {
    display: initial !important;
    align-items: unset !important;
  }
</style>
