import { defineStore } from 'pinia'
import {
  getAllBusinessTypesApi,
  getBusinessTypeApi,
  createBusinessTypeApi,
  updateBusinessTypeApi,
  deleteBusinessTypeApi
} from '@/api/businessTypeService'

export const useBusinessTypeStore = defineStore('businessType', {
  state: () => ({
    businessTypes: [],
    selectedBusinessType: null,
    loading: false,
    error: null
  }),

  getters: {
    activeBusinessTypes: (state) =>
      state.businessTypes.filter((t) => t.is_active),

    getById: (state) => (id) =>
      state.businessTypes.find((t) => t.id === id)
  },

  actions: {
    // ─── Fetch all ────────────────────────────────────────────────────────────
    async fetchBusinessTypes(filters = {}) {
      this.loading = true
      this.error = null
      try {
        const res = await getAllBusinessTypesApi(filters)
        this.businessTypes = res.data.data
      } catch (err) {
        this.error = err?.response?.data?.message || 'Failed to fetch business types'
      } finally {
        this.loading = false
      }
    },

    // ─── Fetch one ────────────────────────────────────────────────────────────
    async fetchBusinessType(id) {
      this.loading = true
      this.error = null
      try {
        const res = await getBusinessTypeApi(id)
        this.selectedBusinessType = res.data.data
      } catch (err) {
        this.error = err?.response?.data?.message || 'Failed to fetch business type'
      } finally {
        this.loading = false
      }
    },

    // ─── Create ───────────────────────────────────────────────────────────────
    async createBusinessType(data) {
      this.loading = true
      this.error = null
      try {
        const res = await createBusinessTypeApi(data)
        this.businessTypes.push(res.data.data)
        return res.data
      } catch (err) {
        this.error = err?.response?.data?.message || 'Failed to create business type'
        throw err
      } finally {
        this.loading = false
      }
    },

    // ─── Update ───────────────────────────────────────────────────────────────
    async updateBusinessType(id, data) {
      this.loading = true
      this.error = null
      try {
        const res = await updateBusinessTypeApi(id, data)
        const idx = this.businessTypes.findIndex((t) => t.id === id)
        if (idx !== -1) this.businessTypes[idx] = res.data.data
        return res.data
      } catch (err) {
        this.error = err?.response?.data?.message || 'Failed to update business type'
        throw err
      } finally {
        this.loading = false
      }
    },

    // ─── Delete ───────────────────────────────────────────────────────────────
    async deleteBusinessType(id) {
      this.loading = true
      this.error = null
      try {
        await deleteBusinessTypeApi(id)
        this.businessTypes = this.businessTypes.filter((t) => t.id !== id)
      } catch (err) {
        this.error = err?.response?.data?.message || 'Failed to delete business type'
        throw err
      } finally {
        this.loading = false
      }
    }
  }
})