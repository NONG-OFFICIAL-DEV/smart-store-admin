import { defineStore } from 'pinia'
import {
  getAllBranchMenusApi,
  getBranchMenuByIdApi,
  createBranchMenuApi,
  updateBranchMenuApi,
  deleteBranchMenuApi,
  unassignBranchMenuApi,
  getAvailableMenusNowApi,
  reorderBranchMenusApi
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

    // Get all branches that have a specific menu, in display order — sorted
    // by sort_order rather than relying on insertion/fetch order, so a
    // drag-reorder (which only patches sort_order in place, not array
    // position) is reflected immediately.
    getBranchesByMenu: state => menuId => {
      return state.branchMenus
        .filter(m => m.menu_id === menuId)
        .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
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
        // Assigning a single branch returns one record; assigning several
        // at once (the form's branch picker is multi-select) returns an
        // array, one per branch — spread it instead of pushing the whole
        // array as a single malformed entry (which getBranchesByMenu()'s
        // flat filter would then silently ignore).
        if (Array.isArray(record)) {
          this.branchMenus.push(...record)
        } else {
          this.branchMenus.push(record)
        }
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

    // ── REORDER (drag-and-drop) ────────────────────────────────────────────
    async reorder(branchMenuIds) {
      await reorderBranchMenusApi(branchMenuIds)
      // Keep local state's sort_order in sync so a subsequent render from
      // `branchMenus` (not just the dragged array itself) stays correct.
      branchMenuIds.forEach((id, index) => {
        const item = this.branchMenus.find(m => m.id === id)
        if (item) item.sort_order = index
      })
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
