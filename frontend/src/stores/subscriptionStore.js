import { defineStore } from 'pinia'
import {
  createSubscriptionApi,
  deleteSubscriptionApi,
  toggleSubscriptionActiveApi,
  cancelSubscriptionApi,
  renewSubscriptionApi,
  recordTenantPaymentApi
} from '../api/subscriptionService'

// Plain mutations only — TenantSubscriptionPage.vue owns its own read
// state (fetched via getPlanByTenantApi), consistent with the rest of this
// store's post-migration shape.
export const useSubscriptionStore = defineStore('subscription', {
  actions: {
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
