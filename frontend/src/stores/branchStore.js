import { defineStore } from 'pinia'
import {
  getAllBranchesApi,
  getBranchByIdApi,
  createBranchApi,
  updateBranchApi,
  deleteBranchApi
} from '../api/branchService'

export const useBranchStore = defineStore('branch', {
  state: () => ({
    branches: [],
    branch: null,
    pagination: {}
  }),

  actions: {
    async fetchBranches(filters) {
      const res = await getAllBranchesApi(filters)
      this.branches = res.data.data
    },
    async fetchBranchById(id) {
      const res = await getBranchByIdApi(id)
      this.branch = res.data.data
    },
    async createBranch(data) {
      const res = await createBranchApi(data)
    },
    async updateBranch(id, data) {
      const res = await updateBranchApi(id, data)
      return res
    },
    async deleteBranch(id) {
      await deleteBranchApi(id)
    }
  }
})
