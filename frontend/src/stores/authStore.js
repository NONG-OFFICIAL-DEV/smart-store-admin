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
    role_name: null,
    logo_url: null,
    currency: null
  }),
  getters: {
    can: state => code => {
      // Owner always has access to everything
      if (state.isOwner) return true
      if (state.isSuperAdmin) return true
      return state.permissions.includes(code)
    },
    // ── Business type helpers ──────────────────────────────────────────────
    isMart: state =>
      ['minimart', 'retail', 'wholesale'].includes(state.bu_type),

    isFood: state =>
      ['restaurant', 'cafe', 'bakery', 'kiosk', 'food_truck'].includes(
        state.bu_type
      )
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
      const d = res.data

      this.me = d.user
      this.unread_notifications_count = d.unread_notifications_count ?? 0
      this.permissions = d.permissions ?? []
      this.isSuperAdmin = d.is_super_admin ?? false
      this.isOwner = d.is_owner ?? false
      // Tenant
      this.tenant_id = d.tenant_id ?? null
      this.bu_name = d.bu_name ?? null
      this.bu_type = d.bu_type ?? null
      this.logo_url = d.logo_url ?? null
      // Branch
      this.branch_id = d.branch_id ?? null
      this.branch_name = d.branch_name ?? null
      // Staff
      this.role_name = d.role_name ?? null
      this.currency = d.currency ?? null
    }
  }
})
