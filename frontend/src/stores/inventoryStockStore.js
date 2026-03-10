import { defineStore } from 'pinia'
import {
  getAllInventoryStocksApi,
  getInventoryStockByIdApi,
  createInventoryStockApi,
  updateInventoryStockApi,
  deleteInventoryStockApi,
} from '@/api/inventoryStockService'

export const useInventoryStockStore = defineStore('inventoryStock', {
  state: () => ({
    stocks:  { data: [], total: 0 },
    stock:   null,
    loading: false,
    error:   null,
  }),

  getters: {
    // Items low on stock (quantity_on_hand <= reorder_point)
    lowStockItems: state =>
      state.stocks.data?.filter(s =>
        s.ingredient?.reorder_point !== null &&
        parseFloat(s.quantity_on_hand) <= parseFloat(s.ingredient?.reorder_point ?? 0)
      ) ?? [],
  },

  actions: {
    async fetchStocks(filters = {}) {
      this.loading = true
      this.error   = null
      try {
        const res    = await getAllInventoryStocksApi(filters)
        this.stocks  = res.data.data
      } catch (e) {
        this.error = e?.response?.data?.message ?? 'Failed to load inventory'
      } finally {
        this.loading = false
      }
    },

    async fetchStockById(id) {
      const res  = await getInventoryStockByIdApi(id)
      this.stock = res.data.data
    },

    async addStock(data) {
      const res = await createInventoryStockApi(data)
      this.stocks.data?.unshift(res.data.data)
      this.stocks.total++
      return res.data.data
    },

    async updateStock(id, data) {
      const res   = await updateInventoryStockApi(id, data)
      const index = this.stocks.data?.findIndex(s => s.id === id)
      if (index !== -1) this.stocks.data[index] = res.data.data
      return res.data.data
    },

    async removeStock(id) {
      await deleteInventoryStockApi(id)
      this.stocks.data  = this.stocks.data?.filter(s => s.id !== id)
      this.stocks.total = Math.max(0, (this.stocks.total ?? 1) - 1)
    },
  },
})