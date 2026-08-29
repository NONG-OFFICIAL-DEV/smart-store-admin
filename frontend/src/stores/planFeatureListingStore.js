import { defineStore } from 'pinia'
import {
  getAllPlanFeatureListingsApi,
  createPlanFeatureListingApi,
  updatePlanFeatureListingApi,
  deletePlanFeatureListingApi
} from '../api/planFeatureListingService'

export const usePlanFeatureListingStore = defineStore('planFeatureListing', {
  state: () => ({
    items: []
  }),

  actions: {
    async fetch() {
      const res = await getAllPlanFeatureListingsApi()
      this.items = res.data.data
    },
    async create(data) {
      await createPlanFeatureListingApi(data)
      await this.fetch()
    },
    async update(id, data) {
      await updatePlanFeatureListingApi(id, data)
      await this.fetch()
    },
    async remove(id) {
      await deletePlanFeatureListingApi(id)
      await this.fetch()
    }
  }
})
