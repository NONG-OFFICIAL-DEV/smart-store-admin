import { defineStore } from 'pinia'
import {
  getSubscriptionByIdApi,
  createSubscriptionApi,
  deleteSubscriptionApi,
  toggleSubscriptionActiveApi,
  cancelSubscriptionApi,
  renewSubscriptionApi,
  recordTenantPaymentApi
} from '../api/subscriptionService'

// List state lives in whichever page renders it (via AppTable, which owns
// its own fetch/pagination) — this store only exposes the mutations, same
// convention as roleStore/auditLogStore's post-migration shape.
export const useSubscriptionStore = defineStore('subscription', {
  state: () => ({
    subscription: null
  }),

  actions: {
    async fetchSubscriptionById(id) {
      const res = await getSubscriptionByIdApi(id)
      this.subscription = res.data.data
    },
    // Assigns a plan to a tenant, or replaces the current one — same
    // endpoint either way (backend cancels-then-creates internally).
    async createSubscription(data) {
      const res = await createSubscriptionApi(data)
      return res.data.data
    },
    async deleteSubscription(id) {
      await deleteSubscriptionApi(id)
    },
    async toggleActive(id) {
      const res = await toggleSubscriptionActiveApi(id)
      return res.data.data
    },
    async cancelSubscription(id) {
      const res = await cancelSubscriptionApi(id)
      return res.data.data
    },
    async renewSubscription(id) {
      const res = await renewSubscriptionApi(id)
      return res.data.data
    },
    async recordPayment(tenantId, data) {
      const res = await recordTenantPaymentApi(tenantId, data)
      return res.data.data
    }
  }
})
