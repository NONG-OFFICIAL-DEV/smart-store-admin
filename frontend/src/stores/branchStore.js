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
    branch: null,
    stats: null,
    tableSummary: null,
    branches: [],
    pagination: {}
  }),
  getters: {
    tables: state => state.branch?.tables ?? [],
    menus: state => state.branch?.menus ?? [],
    staff: state => state.branch?.staff ?? [],
    branchSlug: state => state.branch?.slug ?? '',
    isOpen: state => state.branch?.is_open ?? false
  },
  actions: {
    async fetchBranches(filters) {
      const res = await getAllBranchesApi(filters)
      this.branches = res.data.data
    },
    async fetchBranchById(id) {
      const res = await getBranchByIdApi(id)
      const data = res.data.data
      this.branch = data.branch
      this.stats = data.stats
      this.tableSummary = data.table_summary
    },
    async createBranch(data) {
      const res = await createBranchApi(data)
      return res
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
    }
  }
})
