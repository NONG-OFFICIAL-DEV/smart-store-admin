<template>
  <v-app>
    <!-- <token-expired /> -->
    <!-- reverb: wss://admin.nexstacktech.com -->
    <!-- <Notif ref="notifRef" dismissible :default-timeout="2000" />
    <Confirm ref="confirmRef" /> -->
    <AppConfirmDialog />
    <CoreNotificationAlert />
    <ImpersonationBanner />

    <router-view />
  </v-app>
</template>

<script setup>
  // import ConfirmDialog from '@nong-official-dev/core/plugins/confirm/ConfirmDialog.vue'
  // import NotificationAlert from '@nong-official-dev/core/plugins/notif/NotificationAlert.vue'
  // import Notif from '@/components/global/Notification.vue'
  import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
  import ImpersonationBanner from '@/components/global/ImpersonationBanner.vue'
  // import TokenExpired from '@/components/common/TokenExpired.vue'

  // Refs to components
  // const notifRef = ref(null)
  // const confirmRef = ref(null)

  // Register global methods for both Options and Composition APIs
  // app.config.globalProperties.$notif = notifRef.value?.newAlert
  // app.config.globalProperties.$confirm = confirmRef.value?.open

  // Language is restored synchronously at i18n creation (plugins/i18n.js) and
  // switched via the one shared LanguageSwitch.vue everywhere it's reachable
  // — no separate keyboard shortcut or per-page restore logic needed anymore.
</script>

<style>
  html,
  body {
    overflow-y: auto;
    width: 100%;
    overflow-x: hidden;
    overscroll-behavior-x: none;
    min-height: 100%;
  }

  #app {
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    color: #2c3e50;
    -webkit-text-size-adjust: 100%;
  }

  body {
    /* font-family: 'Poppins', sans-serif; */
    font-family: 'Google Sans', 'Noto Sans Khmer', serif;
    font-optical-sizing: auto;
    touch-action: manipulation;
    position: relative;
    touch-action: pan-y;
  }

  thead tr th {
    font-size: 15px;
    font-weight: bold;
  }

  /* 2. Hide scrollbars but keep scrolling functional */
  ::-webkit-scrollbar {
    display: none;
  }

  .required-star {
    color: #ff5252; /* Vuetify Error Red */
    font-weight: bold;
    margin-left: 2px;
  }
</style>
