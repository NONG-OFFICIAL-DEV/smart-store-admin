<template>
  <sidebar :user="user" v-model:rail="rail" />
  <app-bar :user="user" @toggle="toggleRail" :notifications_count="notifications_count"/>
  <v-main>
    <v-container class="px-4" fluid>
      <router-view />
    </v-container>
  </v-main>
</template>

<script setup>
  import { ref, onMounted ,computed} from 'vue'
  import Sidebar from './Sidebar.vue'
  import AppBar from './AppBar.vue'
  import { useAuthStore } from '@/stores/auth'
  import { useRouter } from 'vue-router'

  const rail = ref(false)
  const user = ref(null)

  const authStore = useAuthStore()
  const router = useRouter()
  const notifications_count = computed(() => authStore.unread_notifications_count)
  // Fetch logged-in user
  onMounted(async () => {
    try {
      await authStore.fetchMe()
      user.value = authStore.me
    } catch {
      await authStore.logout()
      router.push({ name: 'Login' })
    }
  })

  // Toggle rail for sidebar
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