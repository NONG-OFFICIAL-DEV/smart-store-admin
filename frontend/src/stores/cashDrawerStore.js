import { defineStore } from 'pinia'
import {
  getAllCashDrawersApi,
  getCashDrawerByIdApi,
  openCashDrawerApi,
  closeCashDrawerApi,
  updateCashDrawerApi,
  deleteCashDrawerApi,
  getCashDrawerStatusApi,
  cashInApi,
  cashOutApi
} from '../api/cashDrawerService'
import { getAllCashTransactionsApi } from '../api/cashTransactionService'

export const useCashDrawerStore = defineStore('cashDrawer', {
  state: () => ({
    cashDrawers: [],
    cashDrawer: null,
    pagination: {},
    // Live reconciliation figures for the active shift — refetched on open
    // and after every cash-in/cash-out so the Active Shift panel never
    // shows stale numbers.
    status: null,
    transactions: []
  }),

  actions: {
    async fetchCashDrawers(filters) {
      const res = await getAllCashDrawersApi(filters)
      this.cashDrawers = res.data.data
      this.pagination = res.data.meta
    },
    async fetchCashDrawerById(id) {
      const res = await getCashDrawerByIdApi(id)
      this.cashDrawer = res.data.data
    },
    async openCashDrawer(data) {
      const res = await openCashDrawerApi(data)
      this.cashDrawers.unshift(res.data.data)
      return res.data.data
    },
    async closeCashDrawer(id, data) {
      const res = await closeCashDrawerApi(id, data)
      const index = this.cashDrawers.findIndex(item => item.id === id)
      if (index !== -1) this.cashDrawers[index] = res.data.data
      return res.data.data
    },
    async updateCashDrawer(id, data) {
      const res = await updateCashDrawerApi(id, data)
      const index = this.cashDrawers.findIndex(item => item.id === id)
      if (index !== -1) this.cashDrawers[index] = res.data.data
    },
    async deleteCashDrawer(id) {
      await deleteCashDrawerApi(id)
      this.cashDrawers = this.cashDrawers.filter(item => item.id !== id)
    },
    async fetchCashDrawerStatus(id) {
      const res = await getCashDrawerStatusApi(id)
      this.status = res.data.data
      return res.data.data
    },
    async cashIn(id, data) {
      const res = await cashInApi(id, data)
      await this.fetchCashDrawerStatus(id)
      return res.data.data
    },
    async cashOut(id, data) {
      const res = await cashOutApi(id, data)
      await this.fetchCashDrawerStatus(id)
      return res.data.data
    },
    async fetchCashTransactions(filters) {
      const res = await getAllCashTransactionsApi(filters)
      this.transactions = res.data.data
      return res.data
    }
  }
})
