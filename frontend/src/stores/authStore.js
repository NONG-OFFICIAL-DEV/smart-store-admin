import { defineStore } from 'pinia'
import authService from '../api/auth'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    isOwner: false,
    isSuperAdmin: false,
    me: {},
    permissions: [],
    unread_notifications_count: 0,
    token: localStorage.getItem('token') || null,
    bu_name: null,
    branch_name: null,
    logo_url: null
  }),
  getters: {
    can: state => code => {
      // Owner always has access to everything
      if (state.isOwner) return true
      if (state.isSuperAdmin) return true
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
      this.isSuperAdmin = res.data.is_super_admin ?? false
      this.isOwner = res.data.is_owner ?? false
      this.bu_name = res.data.bu_name ?? null
      this.branch_name = res.data.branch_name ?? null
      this.logo_url = res.data.logo_url ?? null
    }
  }
})
