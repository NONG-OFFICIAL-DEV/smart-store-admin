// stores/authStore.js
import { defineStore } from 'pinia'
import authService from '../api/auth'
import { PLANS } from '@/constants/plan'
import { BU_CATEGORIES } from '@/constants/businessTypes'

export { BU_CATEGORIES }   // re-export so existing imports still work

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token:    localStorage.getItem('token') || null,
    user:     null,
    me:       {},

    isOwner:      false,
    isSuperAdmin: false,
    me: {},
    permissions: [],
    unread_notifications_count: 0,
    token: localStorage.getItem('token') || null,
    refreshToken: localStorage.getItem('refresh_token') || null,
    bu_name: null,
    bu_type: null,
    branch_id: null,
    branch_name: null,
    role_name: null,
    logo_url: null,
    plan: null,
    currency: null,
    role_name:    null,
    permissions:  [],

    tenant_id: null,
    bu_name:   null,
    bu_type:   null,
    logo_url:  null,
    plan:      null,
    currency:  null,
    locale:    null,

    branch_id:   null,
    branch_name: null,

    unread_notifications_count: 0,
  }),

  getters: {
    can: state => code => {
      if (state.isOwner || state.isSuperAdmin) return true
      return state.permissions.includes(code)
    },
    // isFree:       state => state.plan === PLANS.FREE,
    // isStart:      state => state.plan === PLANS.START,
    // isPro:        state => state.plan === PLANS.PRO,
    // isEnterprise: state => state.plan === PLANS.ENTERPRISE,
    //   if (state.isSuperAdmin || state.isOwner) return true
    //   return state.permissions.includes(code)
    // },

    isFree:       state => state.plan === PLANS.FREE,
    isStarter:    state => state.plan === PLANS.START,
    isPro:        state => state.plan === PLANS.PRO,
    isEnterprise: state => state.plan === PLANS.ENTERPRISE,

    hasPlan: state => level => {
      const order = [PLANS.FREE, PLANS.START, PLANS.PRO, PLANS.ENTERPRISE]
      return order.indexOf(state.plan) >= order.indexOf(level)
    },
    // Category helpers — driven entirely by BU_CATEGORIES from businessTypes.js
    isFood: state => BU_CATEGORIES.food?.has(state.bu_type) ?? false,
    isMart: state => BU_CATEGORIES.mart?.has(state.bu_type) ?? false,

    // Generic: authStore.isCategory('health') → true/false
    isCategory: state => category =>
      BU_CATEGORIES[category]?.has(state.bu_type) ?? false,
  },

  actions: {
    async login({ email, password }) {
      const response = await authService.userLogin(email, password)
      if (response.data.status === 'success') {
        this._applySession(response.data)
        await this.fetchMe()
        this.token = response.data.token
        this.user  = response.data.user
        localStorage.setItem('token', response.data.token)
      }
      return response
    },

    async loginByPin(pin_code) {
      const branch_id = localStorage.getItem('branch_id')
      const response = await authService.loginByPin(pin_code, branch_id)
      if (response.data.status === 'success') {
        this._applySession(response.data)
        this.token = response.data.token
        this.user  = response.data.user
        localStorage.setItem('token', response.data.token)
        await this.fetchMe()
      }
      return response
    },

    async logout() {
      await authService.userLogout().catch(() => {})
      this._clearSession()
      this.$reset()
      localStorage.removeItem('token')
    },

    async fetchMe() {
      const res = await authService.me().catch(() => {})
      const d   = res?.data ?? {}

      this.me          = d.user
      this.permissions = d.permissions ?? []
      this.isSuperAdmin = d.is_super_admin ?? false
      this.isOwner     = d.is_owner ?? false
      this.tenant_id   = d.tenant_id ?? null
      this.bu_name     = d.bu_name ?? null
      this.bu_type     = d.bu_type ?? null
      this.logo_url    = d.logo_url ?? null
      this.branch_id   = d.branch_id ?? null
      this.branch_name = d.branch_name ?? null
      this.role_name   = d.role_name ?? null
      this.currency    = d.currency ?? null
      this.plan        = d.plan ?? null
      this.unread_notifications_count = d.unread_notifications_count ?? 0

      // Persist branch_id so PIN login can use it next time
      if (d.branch_id) localStorage.setItem('branch_id', d.branch_id)
    },

    // ── Private helpers ──────────────────────────────────────────────────
    _applySession(data) {
      this.token        = data.token
      this.refreshToken = data.refresh_token
      this.user         = data.user
      localStorage.setItem('token', data.token)
      localStorage.setItem('refresh_token', data.refresh_token)
    },

    _clearSession() {
      this.token        = null
      this.refreshToken = null
      this.user         = null
      this.me           = {}
      this.permissions  = []
      this.isOwner      = false
      this.isSuperAdmin = false
      localStorage.removeItem('token')
      localStorage.removeItem('refresh_token')
      localStorage.removeItem('branch_id')
      this.me           = d.user           ?? {}
      this.permissions  = d.permissions    ?? []
      this.isSuperAdmin = d.is_super_admin ?? false
      this.isOwner      = d.is_owner       ?? false
      this.role_name    = d.role_name      ?? null

      this.tenant_id = d.tenant_id ?? null
      this.bu_name   = d.bu_name   ?? null
      this.bu_type   = d.business_type?.code ?? d.bu_type ?? null
      this.logo_url  = d.logo_url  ?? null
      this.plan      = d.plan      ?? null
      this.currency  = d.currency  ?? null
      this.locale    = d.locale    ?? null

      this.branch_id   = d.branch_id   ?? null
      this.branch_name = d.branch_name ?? null

      this.unread_notifications_count = d.unread_notifications_count ?? 0
    },
  },
})