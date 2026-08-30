// stores/authStore.js
import { defineStore } from 'pinia'
import authService from '../api/auth'
import { PLANS } from '@/constants/plan'
import { BU_CATEGORIES } from '@/constants/businessTypes'
import { connectEcho, disconnectEcho } from '@/utils/echo'
import { impersonateTenantApi } from '@/api/tenantService'

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
    features:     [],
    mustChangePassword: false,

    tenant_id: null,
    bu_name:   null,
    bu_type:   null,
    business_type_id: null,
    logo_url:  null,
    plan:      null,
    currency:  null,
    locale:    null,
    subscription_status: null,
    trial_ends_at: null,

    branch_id:   null,
    branch_name: null,

    unread_notifications_count: 0,

    // { tenantName } while a super admin is impersonating a tenant owner,
    // else null. Only ever set/cleared by impersonateTenant()/returnToAdmin().
    impersonating: null,
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

    // Nav-level only — "does ANY of my branches support this" (owner) or
    // "does my one branch support this" (staff). Real per-branch enforcement
    // still happens server-side via the feature:CODE route middleware.
    hasFeature: state => code => state.features.includes(code),

    // Category helpers — driven entirely by BU_CATEGORIES from businessTypes.js
    isFood: state => BU_CATEGORIES.food?.has(state.bu_type) ?? false,
    isMart: state => BU_CATEGORIES.mart?.has(state.bu_type) ?? false,

    // Generic: authStore.isCategory('health') → true/false
    isCategory: state => category =>
      BU_CATEGORIES[category]?.has(state.bu_type) ?? false,

    // Days left in the trial, or null if not on a trial / no end date.
    // Super admin has no tenant subscription of their own.
    trialDaysLeft: state => {
      if (state.subscription_status !== 'trial' || !state.trial_ends_at) return null
      const diff = Math.ceil((new Date(state.trial_ends_at) - new Date()) / 864e5)
      return diff >= 0 ? diff : 0
    },
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

    // Swaps only the access token — refresh_token in localStorage keeps
    // pointing at the admin's OWN session on purpose. The impersonation
    // token has no refresh token of its own, so once it expires the very
    // next request's 401 silently refreshes back into the admin's own
    // identity via that untouched refresh_token — natural, safe expiry
    // with no separate revoke endpoint needed.
    async impersonateTenant(tenantId, tenantName) {
      const { data } = await impersonateTenantApi(tenantId)
      sessionStorage.setItem('pre_impersonation_token', this.token)
      this.token = data.data.access_token
      localStorage.setItem('token', data.data.access_token)
      this.impersonating = { tenantName }
      await this.fetchMe()
    },

    async returnToAdmin() {
      const adminToken = sessionStorage.getItem('pre_impersonation_token')
      if (!adminToken) return
      sessionStorage.removeItem('pre_impersonation_token')
      this.token = adminToken
      localStorage.setItem('token', adminToken)
      this.impersonating = null
      await this.fetchMe()
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
      this.features     = d.features       ?? []
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
      this.subscription_status = d.subscription_status ?? null
      this.trial_ends_at       = d.trial_ends_at        ?? null

      this.branch_id   = d.branch_id   ?? null
      this.branch_name = d.branch_name ?? null

      this.unread_notifications_count = d.unread_notifications_count ?? 0
      this.mustChangePassword = d.must_change_password ?? false

      if (this.me?.id) connectEcho()
    },
  },
})