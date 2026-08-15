import { defineStore } from 'pinia'
import { ref } from 'vue'
import { appDensity } from '@/plugins/vuetify'

export const usePreferencesStore = defineStore('preferences', () => {
  const density = ref(localStorage.getItem('density') || 'default')

  function setDensity(value) {
    density.value = value
    appDensity.value = value  // ✅ triggers the watch in vuetify.js
    localStorage.setItem('density', value)
  }

  return { density, setDensity }
})