// stores/authStore.js
import { defineStore } from 'pinia'
import authService from '../api/auth'
import { PLANS } from '@/constants/plan'
import { connectEcho, disconnectEcho } from '@/utils/echo'
import { impersonateTenantApi } from '@/api/tenantService'

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
    bu_category: null,
    business_type_id: null,
    logo_url:  null,
    plan:      null,
    currency:  null,
    locale:    null,
    subscription_status: null,
    trial_ends_at: null,

    // Which POS controls actually show on the POS screen — a tenant-
    // configurable subset (Settings > POS). Defaults match the backend's
    // Tenant::DEFAULT_POS_SETTINGS so the POS screens work sensibly even
    // before fetchMe() resolves.
    posSettings: {
      order_types: ['dine_in', 'takeaway', 'delivery'],
      customer_selection: true,
      order_notes: true
    },

    branch_id:   null,
    branch_name: null,
    // Only ever set for a Staff row — null for owners/super admins, who
    // have no Staff record and so can't personally open a cash drawer
    // (CashDrawer is staff_id-scoped).
    staff_id:    null,

    // The branch currently being operated on (POS, etc.) — distinct from
    // branch_id above. Staff are pinned to their one assigned branch
    // (always forced equal to branch_id, never user-changeable). An owner
    // has no single branch_id (they can see all of them), so this is the
    // one they've actively picked via the sidebar branch switcher —
    // persisted across sessions, restored from localStorage on load.
    activeBranchId: localStorage.getItem('active_branch_id') || null,

    unread_notifications_count: 0,

    // { tenantName } while a super admin is impersonating a tenant owner,
    // else null. Only ever set/cleared by impersonateTenant()/returnToAdmin().
    impersonating: null,

    // { code, message } when the most recent API response was blocked by
    // EnsureSubscriptionActive (TENANT_SUSPENDED / SUBSCRIPTION_STATUS_BLOCKED),
    // else null. Set/cleared entirely by api.js's response interceptor —
    // not derived from subscription_status, since TENANT_SUSPENDED has no
    // corresponding subscription_status value at all (it's a separate flag).
    blockedApiError: null,
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

    // Category helpers — driven by the tenant's actual business_types.category
    // column (see /me's bu_category), not a hardcoded frontend list — a new
    // business type just needs its category set once in Manage Business Types,
    // no frontend code change required.
    isFood: state => state.bu_category === 'food',
    isMart: state => state.bu_category === 'mart',

    // Generic: authStore.isCategory('food') → true/false
    isCategory: state => category => state.bu_category === category,

    // Days left in the trial, or null if not on a trial / no end date.
    // Super admin has no tenant subscription of their own.
    trialDaysLeft: state => {
      if (state.subscription_status !== 'trial' || !state.trial_ends_at) return null
      const diff = Math.ceil((new Date(state.trial_ends_at) - new Date()) / 864e5)
      return diff >= 0 ? diff : 0
    },

  },

  actions: {
    setBlockedApiError(error) {
      this.blockedApiError = error
    },

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
      this.bu_category = d.bu_category ?? null
      this.business_type_id = d.business_type_id ?? null
      this.logo_url  = d.logo_url  ?? null
      this.plan      = d.plan      ?? null
      this.currency  = d.currency  ?? null
      this.locale    = d.locale    ?? null
      this.subscription_status = d.subscription_status ?? null
      this.trial_ends_at       = d.trial_ends_at        ?? null
      if (d.pos_settings) this.posSettings = d.pos_settings

      this.branch_id   = d.branch_id   ?? null
      this.branch_name = d.branch_name ?? null
      this.staff_id    = d.staff_id    ?? null

      // Staff can never work outside their assigned branch — force it,
      // overriding any stale localStorage value (e.g. left over from a
      // previous owner session on a shared device).
      if (this.branch_id) this.setActiveBranch(this.branch_id)

      this.unread_notifications_count = d.unread_notifications_count ?? 0
      this.mustChangePassword = d.must_change_password ?? false

      // /me sits outside EnsureSubscriptionActive's gate, so this is the one
      // reliable source of "am I blocked" that survives a page refresh —
      // the api.js interceptor's event-driven update reacts faster to a
      // live 403, but a full reload has no 403 to react to yet (the banner
      // would otherwise silently vanish on refresh, even though the block
      // is still in effect). Mirrors EnsureSubscriptionActive's exact
      // precedence: tenant suspension first, then subscription status.
      if (d.tenant_is_active === false) {
        this.blockedApiError = { code: 'TENANT_SUSPENDED', message: null }
      } else if (['cancelled', 'suspended'].includes(d.subscription_status)) {
        this.blockedApiError = { code: 'SUBSCRIPTION_STATUS_BLOCKED', message: null }
      } else {
        this.blockedApiError = null
      }

      if (this.me?.id) connectEcho()
    },

    setActiveBranch(branchId) {
      this.activeBranchId = branchId
      if (branchId) localStorage.setItem('active_branch_id', branchId)
      else localStorage.removeItem('active_branch_id')
    },
  },
})