<template>
  <v-app>
    <!-- <token-expired /> -->
    <Notif ref="notifRef" dismissible :default-timeout="2000" />
    <Confirm ref="confirmRef" />
    <router-view />
    <Loading />
  </v-app>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, getCurrentInstance } from 'vue'
import Notif from '@/components/global/Notification.vue'
import Confirm from '@/components/global/Confirm.vue'
import Loading from '@/components/global/Loading.vue'
// import TokenExpired from '@/components/common/TokenExpired.vue'

// Refs to components
const notifRef = ref(null)
const confirmRef = ref(null)

// Get current Vue instance
const instance = getCurrentInstance()

// Keyboard switcher
const handleKeyDown = (e) => {
  if (e.shiftKey && e.ctrlKey && e.key === 'L') {
    e.preventDefault()
    const currentLocale = instance.appContext.config.globalProperties.$i18n.locale
    const newLocale = currentLocale === 'en' ? 'km' : 'en'
    instance.appContext.config.globalProperties.$i18n.locale = newLocale
    localStorage.setItem('lang', newLocale)
  }
}

onMounted(() => {
  const app = instance.appContext.app

  // Register global methods for both Options and Composition APIs
  app.config.globalProperties.$notif = notifRef.value?.newAlert
  app.config.globalProperties.$confirm = confirmRef.value?.open

  // Restore saved language preference
  const savedLang = localStorage.getItem('lang')
  if (savedLang) {
    instance.appContext.config.globalProperties.$i18n.locale = savedLang
  }

  // Bind keyboard event
  document.addEventListener('keydown', handleKeyDown)
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleKeyDown)
})
</script>

<style>
html,
body {
  overflow-y: auto;
  width: 100%;
  overflow-x: hidden;
  overscroll-behavior-x: none;
  height: 100%;
}

#app {
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  color: #2c3e50;
  -webkit-text-size-adjust: 100%;
}

body {
  font-family: 'Poppins', sans-serif;
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
