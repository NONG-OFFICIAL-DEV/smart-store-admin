import { defineStore } from 'pinia'
import authService from '../api/auth'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    isOwner: false,
    me: {},
    permissions: [],
    unread_notifications_count: 0,
    token: localStorage.getItem('token') || null
  }),
  getters: {
    can: state => code => {
      // Owner always has access to everything
      if (state.isOwner) return true
      return state.permissions.includes(code)
    }
  },
  actions: {
    //how to use it see in file Login.vue
    async login({ email, password }) {
      const response = await authService.userLogin(email, password)
      if (response.data.status === 'success') {
        this.token = response.data.token
        this.user = response.data.user
        localStorage.setItem('token', response.data.token)
      }
      return response
    },
    async logout() {
      // optional: call API to invalidate JWT on backend
      await authService.userLogout().catch(() => {})

      // remove token & user
      this.token = null
      this.user = null
      localStorage.removeItem('token')
    },
    async fetchMe() {
      const res = await authService.me().catch(() => {})
      this.me = res.data.user
      this.unread_notifications_count = res.data.unread_notifications_count
      this.permissions = res.data.permissions ?? []
      this.isOwner = res.data.is_owner ?? false
    }
  }
})
