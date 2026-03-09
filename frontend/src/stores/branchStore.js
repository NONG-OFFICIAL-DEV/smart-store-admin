import { defineStore } from 'pinia'
import {
  getAllBranchesApi,
  getBranchByIdApi,
  createBranchApi,
  updateBranchApi,
  deleteBranchApi,
  toggleOpen
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
    },
    async toggleOpen(id) {
      const res = await toggleOpen(id)
      if (this.branch) {
        this.branch.is_open = res.data.data.is_open
      }
    },
  }
})
