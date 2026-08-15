import { defineStore } from 'pinia'
import {
  getAllBranchMenusApi,
  getBranchMenuByIdApi,
  createBranchMenuApi,
  updateBranchMenuApi,
  deleteBranchMenuApi,
  unassignBranchMenuApi,
  getAvailableMenusNowApi
} from '@/api/menuBranchService'

export const useBranchMenuStore = defineStore('branchMenu', {
  state: () => ({
    branchMenus: [],
    branchMenu: null,
    availableMenus: [],
    loading: false,
    error: null,
    pagination: {
      total: 0,
      per_page: 10,
      current_page: 1
    }
  }),

  getters: {
    // Get all menus for a specific branch from state
    getMenusByBranch: state => branchId => {
      return state.branchMenus.filter(m => m.branch_id === branchId)
    },

    // Get all branches that have a specific menu
    getBranchesByMenu: state => menuId => {
      return state.branchMenus.filter(m => m.menu_id === menuId)
    },

    isLoading: state => state.loading,
    hasError: state => !!state.error
  },

  actions: {
    // ── FETCH ALL ─────────────────────────────────────────────────────────
    async fetchAll(filters = {}) {
      this.loading = true
      this.error = null
      try {
        const res = await getAllBranchMenusApi(filters)
        this.branchMenus = res.data.data
        if (res.data.pagination) {
          this.pagination = res.data.pagination
        }
      } catch (err) {
        this.error = err.response?.data?.error || 'Failed to fetch branch menus'
      } finally {
        this.loading = false
      }
    },

    // ── FETCH ONE ─────────────────────────────────────────────────────────
    async fetchById(id) {
      this.loading = true
      this.error = null
      this.branchMenu = null
      try {
        const res = await getBranchMenuByIdApi(id)
        this.branchMenu = res.data.data
      } catch (err) {
        this.error = err.response?.data?.error || 'Failed to fetch branch menu'
      } finally {
        this.loading = false
      }
    },

    // ── CREATE ────────────────────────────────────────────────────────────
    async create(data) {
      this.loading = true
      this.error = null
      try {
        const res = await createBranchMenuApi(data)
        const record = res.data.data
        // Add to local state without re-fetching
        this.branchMenus.push(record)
        return { success: true, data: record }
      } catch (err) {
        this.error =
          err.response?.data?.error || 'Failed to assign menu to branch'
        return { success: false, error: this.error }
      } finally {
        this.loading = false
      }
    },

    // ── UPDATE ────────────────────────────────────────────────────────────
    async update(id, data) {
      this.loading = true
      this.error = null
      try {
        const res = await updateBranchMenuApi(id, data)
        const record = res.data.data
        // Update in local state
        const index = this.branchMenus.findIndex(m => m.id === id)
        if (index !== -1) {
          this.branchMenus[index] = record
        }
        return { success: true, data: record }
      } catch (err) {
        this.error = err.response?.data?.error || 'Failed to update branch menu'
        return { success: false, error: this.error }
      } finally {
        this.loading = false
      }
    },

    // ── DELETE BY ID ──────────────────────────────────────────────────────
    async remove(id) {
      this.loading = true
      this.error = null
      try {
        await deleteBranchMenuApi(id)
        // Remove from local state
        this.branchMenus = this.branchMenus.filter(m => m.id !== id)
        return { success: true }
      } catch (err) {
        this.error = err.response?.data?.error || 'Failed to remove branch menu'
        return { success: false, error: this.error }
      } finally {
        this.loading = false
      }
    },

    // ── UNASSIGN BY BRANCH + MENU ID ──────────────────────────────────────
    async unassign(branchId, menuId) {
      this.loading = true
      this.error = null
      try {
        await unassignBranchMenuApi({ branch_id: branchId, menu_id: menuId })
        // Remove from local state
        this.branchMenus = this.branchMenus.filter(
          m => !(m.branch_id === branchId && m.menu_id === menuId)
        )
        return { success: true }
      } catch (err) {
        this.error = err.response?.data?.error || 'Failed to unassign menu'
        return { success: false, error: this.error }
      } finally {
        this.loading = false
      }
    },

    // ── GET AVAILABLE MENUS RIGHT NOW ─────────────────────────────────────
    async fetchAvailableNow(branchId) {
      this.loading = true
      this.error = null
      this.availableMenus = []
      try {
        const res = await getAvailableMenusNowApi(branchId)
        this.availableMenus = res.data.data
      } catch (err) {
        this.error =
          err.response?.data?.error || 'Failed to fetch available menus'
      } finally {
        this.loading = false
      }
    },

    // ── RESET STATE ───────────────────────────────────────────────────────
    reset() {
      this.branchMenus = []
      this.branchMenu = null
      this.availableMenus = []
      this.loading = false
      this.error = null
    }
  }
})
