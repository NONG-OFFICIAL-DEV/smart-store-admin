import { defineStore } from 'pinia'
import { getAllBranchProductOverridesApi, getBranchProductOverrideByIdApi, createBranchProductOverrideApi, updateBranchProductOverrideApi, deleteBranchProductOverrideApi } from '../api/branchProductOverrideService'

export const useBranchProductOverrideStore = defineStore('branchProductOverride', {
  state: () => ({
    branchProductOverrides: [],
    branchProductOverride:  null,
    pagination:             {},
  }),

  actions: {
    async fetchBranchProductOverrides(filters) {
      const res                     = await getAllBranchProductOverridesApi(filters)
      this.branchProductOverrides   = res.data.data.data
      this.pagination               = res.data.data
    },
    async fetchBranchProductOverrideById(id) {
      const res                    = await getBranchProductOverrideByIdApi(id)
      this.branchProductOverride   = res.data.data
    },
    async createBranchProductOverride(data) {
      const res = await createBranchProductOverrideApi(data)
      this.branchProductOverrides.unshift(res.data.data)
    },
    async updateBranchProductOverride(id, data) {
      const res   = await updateBranchProductOverrideApi(id, data)
      const index = this.branchProductOverrides.findIndex(item => item.id === id)
      if (index !== -1) this.branchProductOverrides[index] = res.data.data
    },
    async deleteBranchProductOverride(id) {
      await deleteBranchProductOverrideApi(id)
      this.branchProductOverrides = this.branchProductOverrides.filter(item => item.id !== id)
    },
  },
})