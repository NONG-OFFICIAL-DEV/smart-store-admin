import { defineStore } from 'pinia'
import { getAllCashDrawersApi, getCashDrawerByIdApi, createCashDrawerApi, updateCashDrawerApi, deleteCashDrawerApi } from '../api/cashDrawerService'

export const useCashDrawerStore = defineStore('cashDrawer', {
  state: () => ({
    cashDrawers: [],
    cashDrawer:  null,
    pagination:  {},
  }),

  actions: {
    async fetchCashDrawers(filters) {
      const res          = await getAllCashDrawersApi(filters)
      this.cashDrawers   = res.data.data.data
      this.pagination    = res.data.data
    },
    async fetchCashDrawerById(id) {
      const res        = await getCashDrawerByIdApi(id)
      this.cashDrawer  = res.data.data
    },
    async createCashDrawer(data) {
      const res = await createCashDrawerApi(data)
      this.cashDrawers.unshift(res.data.data)
    },
    async updateCashDrawer(id, data) {
      const res   = await updateCashDrawerApi(id, data)
      const index = this.cashDrawers.findIndex(item => item.id === id)
      if (index !== -1) this.cashDrawers[index] = res.data.data
    },
    async deleteCashDrawer(id) {
      await deleteCashDrawerApi(id)
      this.cashDrawers = this.cashDrawers.filter(item => item.id !== id)
    },
  },
})