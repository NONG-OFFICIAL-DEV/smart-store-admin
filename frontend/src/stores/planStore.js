import { defineStore } from 'pinia'
import {
  getAllPlansApi,
  getPlanByIdApi,
  createPlanApi,
  updatePlanApi,
  deletePlanApi,
  togglePlanActiveApi,
  getPlanByTenantApi,
  getBillingPlansApi,
  changePlanApi,
  renewSubscriptionApi
} from '../api/planService'

export const usePlanStore = defineStore('plan', {
  state: () => ({
    plans: [],
    plan: {}
  }),

  actions: {
    async fetchPlans(filters) {
      const res = await getAllPlansApi(filters)
      this.plans = res.data.data
    },
    async fetchPlanById(id) {
      const res = await getPlanByIdApi(id)
      this.plan = res.data.data
    },
    async createPlan(data) {
      const res = await createPlanApi(data)
      this.fetchPlans()
      return res
    },
    async updatePlan(id, data) {
      const res = await updatePlanApi(id, data)
      const index = this.plans.findIndex(item => item.id === id)
      if (index !== -1) this.plans[index] = res.data.data
      this.fetchPlans()
    },
    async deletePlan(id) {
      await deletePlanApi(id)
      this.plans = this.plans.filter(item => item.id !== id)
      this.fetchPlans()
    },
    async fetchPlansByTenant(id) {
      const res = await getPlanByTenantApi(id)
      this.plan = res.data.data
    },
    // Toggle is_active — suspend or activate
    async toggleActive(id) {
      const res = await togglePlanActiveApi(id)
      const index = this.plans.findIndex(p => p.id === id)
      if (index !== -1) this.plans[index] = res.data.data
    },

    // ── Self-service billing (Tenant Owner) ──────────────────────────────────
    async fetchAvailablePlans() {
      const res = await getBillingPlansApi()
      this.plans = res.data.data
    },
    async changePlan(payload) {
      const res = await changePlanApi(payload)
      return res.data.data
    },
    async renewSubscription() {
      const res = await renewSubscriptionApi()
      return res.data.data
    }
  }
})