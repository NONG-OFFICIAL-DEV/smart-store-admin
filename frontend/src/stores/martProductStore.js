// stores/martProductStore.js
import { defineStore } from 'pinia'
import api from '@/api/api'

export const useMartProductStore = defineStore('martProduct', {
  state: () => ({
    products: [],
    options: [],
    pagination: {},
    loading: false
  }),

  getters: {
    // Quick lookup by id
    byId: state => id => state.products.find(p => p.id === id) ?? null,

    // Products with stock at or below reorder level
    lowStock: state =>
      state.products.filter(
        p => p.reorder_level != null && p.stock_quantity <= p.reorder_level
      ),

    lowStockCount: state =>
      state.products.filter(
        p => p.reorder_level != null && p.stock_quantity <= p.reorder_level
      ).length
  },

  actions: {
    async fetchMartProducts(filters = {}) {
      this.loading = true
      try {
        const res = await api.get('v1/mart/products', { params: filters })
        this.products = res.data.data
        this.pagination = res.data
      } finally {
        this.loading = false
      }
    },
    async fetchMartProductOptions(filters = {}) {
      this.loading = true
      try {
        const res = await api.get('v1/mart/products', { params: filters })
        this.options = res.data.data
      } finally {
        this.loading = false
      }
    },

    // Optimistic stock update after a sale / adjustment
    updateStock(productId, newQty) {
      const p = this.products.find(p => p.id === productId)
      if (p) p.stock_quantity = newQty
    },

    // Called after PO receive — increment local stock
    incrementStock(productId, baseQtyAdded) {
      const p = this.products.find(p => p.id === productId)
      if (p) p.stock_quantity = parseFloat(p.stock_quantity) + baseQtyAdded
    }
  }
})
