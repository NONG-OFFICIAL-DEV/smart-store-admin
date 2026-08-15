// stores/authStore.js
import { defineStore } from 'pinia'
import authService from '../api/auth'
import { PLANS } from '@/constants/plan'
import { BU_CATEGORIES } from '@/constants/businessTypes'
import { connectEcho, disconnectEcho } from '@/utils/echo'

export { BU_CATEGORIES }   // re-export so existing imports still work

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token:    localStorage.getItem('token') || null,
    user:     null,
    me:       {},

    isOwner:      false,
    isSuperAdmin: false,
    role_name:    null,
    permissions:  [],
    mustChangePassword: false,

    tenant_id: null,
    bu_name:   null,
    bu_type:   null,
    business_type_id: null,
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
      if (state.isSuperAdmin || state.isOwner) return true
      return state.permissions.includes(code)
    },

    canAny: state => (...codes) => {
      if (state.isSuperAdmin || state.isOwner) return true
      return codes.some(code => state.permissions.includes(code))
    },

    canAll: state => (...codes) => {
      if (state.isSuperAdmin || state.isOwner) return true
      return codes.every(code => state.permissions.includes(code))
    },

    isFree:       state => state.plan === PLANS.FREE,
    isStarter:    state => state.plan === PLANS.START,
    isPro:        state => state.plan === PLANS.PRO,
    isEnterprise: state => state.plan === PLANS.ENTERPRISE,

    hasPlan: state => level => {
      if (state.isSuperAdmin) return true
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
        this.token = response.data.token
        this.user  = response.data.user
        this.mustChangePassword = response.data.must_change_password ?? false
        localStorage.setItem('token', response.data.token)
      }
      return response
    },

    async loginByPin(pin_code, branch_id = null) {
      const response = await authService.loginByPin(pin_code, branch_id)
      if (response.data.status === 'success') {
        this.token = response.data.token
        this.user  = response.data.user
        localStorage.setItem('token', response.data.token)
        await this.fetchMe()
      }
      return response
    },

    async logout() {
      await authService.userLogout().catch(() => {})
      disconnectEcho()
    },

    // Silently exchange the current (possibly just-expired) token for a
    // fresh one. Used by the api.js response interceptor on 401s.
    async refreshToken() {
      const res = await authService.refresh()
      this.token = res.data.token
      localStorage.setItem('token', res.data.token)
      return res.data.token
    },

    async fetchMe() {
      const res = await authService.me().catch(() => {})
      const d   = res?.data ?? {}

      this.me           = d.user           ?? {}
      this.permissions  = d.permissions    ?? []
      this.isSuperAdmin = d.is_super_admin ?? false
      this.isOwner      = d.is_owner       ?? false
      this.role_name    = d.role_name      ?? null

      this.tenant_id = d.tenant_id ?? null
      this.bu_name   = d.bu_name   ?? null
      this.bu_type   = d.bu_type   ?? null
      this.business_type_id = d.business_type_id ?? null
      this.logo_url  = d.logo_url  ?? null
      this.plan      = d.plan      ?? null
      this.currency  = d.currency  ?? null
      this.locale    = d.locale    ?? null

      this.branch_id   = d.branch_id   ?? null
      this.branch_name = d.branch_name ?? null

      this.unread_notifications_count = d.unread_notifications_count ?? 0
      this.mustChangePassword = d.must_change_password ?? false

      if (this.me?.id) connectEcho()
    },
  },
})