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

      // Password step succeeded but the account has 2FA enabled — no
      // token is issued yet, the caller (Login.vue) must complete the
      // separate verifyTwoFactor() step before any session actually exists.
      if (response.data.requires_two_factor) {
        return response
      }

      if (response.data.status === 'success') {
        this.storeTokenPair(response.data)
        this.user  = response.data.user
        this.mustChangePassword = response.data.must_change_password ?? false
      }
      return response
    },

    async verifyTwoFactor(twoFactorToken, code) {
      const response = await authService.verifyTwoFactor(twoFactorToken, code)
      if (response.data.status === 'success') {
        this.storeTokenPair(response.data)
        this.user  = response.data.user
        this.mustChangePassword = response.data.must_change_password ?? false
      }
      return response
    },

    async register(payload) {
      const response = await authService.register(payload)
      if (response.data.success) {
        this.storeTokenPair(response.data.data)
      }
      return response
    },

    async forgotPassword(email) {
      return authService.forgotPassword(email)
    },

    async resetPassword(payload) {
      return authService.resetPassword(payload)
    },

    async logout() {
      const refreshToken = localStorage.getItem('refresh_token')
      await authService.userLogout(refreshToken).catch(() => {})
      localStorage.removeItem('refresh_token')
      disconnectEcho()
    },

    // Silently exchange the current refresh token for a fresh access+refresh
    // pair. Used by the api.js response interceptor on 401s. The refresh
    // token itself rotates on every call — the old one is dead the instant
    // this succeeds, so the new value must always replace it in storage.
    async refreshToken() {
      const refreshToken = localStorage.getItem('refresh_token')
      const res = await authService.refresh(refreshToken)
      this.storeTokenPair(res.data)
      return res.data.token
    },

    storeTokenPair(data) {
      this.token = data.token
      localStorage.setItem('token', data.token)
      if (data.refresh_token) {
        localStorage.setItem('refresh_token', data.refresh_token)
      }
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