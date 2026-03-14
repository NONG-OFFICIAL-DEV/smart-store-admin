// stores/productUnitStore.js
import { defineStore } from 'pinia'
import {
  getProductUnitsApi,
  createProductUnitApi,
  updateProductUnitApi,
  deleteProductUnitApi,
  getProductUnitNameApi
} from '@/api/productUnitService'

export const useProductUnitStore = defineStore('productUnit', {
  state: () => ({
    units: [],
    productId: null,
    loading: false
  }),

  actions: {
    async fetchUnits(productId) {
      this.loading = true
      this.productId = productId
      try {
        const res = await getProductUnitsApi(productId)
        this.units = res.data.data
      } finally {
        this.loading = false
      }
    },
    async fetchUnitName() {
      const res = await getProductUnitNameApi()
      return res
    },

    async createUnit(productId, data) {
      const res = await createProductUnitApi(productId, data)
      this.units.push(res.data.data)
      return res.data.data
    },

    async updateUnit(productId, id, data) {
      const res = await updateProductUnitApi(productId, id, data)
      const index = this.units.findIndex(u => u.id === id)
      if (index !== -1) this.units[index] = res.data.data
      return res.data.data
    },

    async deleteUnit(productId, id) {
      await deleteProductUnitApi(productId, id)
      this.units = this.units.filter(u => u.id !== id)
    }
  }
})
