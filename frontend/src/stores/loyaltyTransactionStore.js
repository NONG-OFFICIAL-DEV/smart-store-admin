import { defineStore } from 'pinia'
import { getAllLoyaltyTransactionsApi, getLoyaltyTransactionByIdApi, createLoyaltyTransactionApi, updateLoyaltyTransactionApi, deleteLoyaltyTransactionApi } from '../api/loyaltyTransactionService'

export const useLoyaltyTransactionStore = defineStore('loyaltyTransaction', {
  state: () => ({
    loyaltyTransactions: [],
    loyaltyTransaction:  null,
    pagination:          {},
  }),

  actions: {
    async fetchLoyaltyTransactions(filters) {
      const res                    = await getAllLoyaltyTransactionsApi(filters)
      this.loyaltyTransactions     = res.data.data.data
      this.pagination              = res.data.data
    },
    async fetchLoyaltyTransactionById(id) {
      const res                   = await getLoyaltyTransactionByIdApi(id)
      this.loyaltyTransaction     = res.data.data
    },
    async createLoyaltyTransaction(data) {
      const res = await createLoyaltyTransactionApi(data)
      this.loyaltyTransactions.unshift(res.data.data)
    },
    async updateLoyaltyTransaction(id, data) {
      const res   = await updateLoyaltyTransactionApi(id, data)
      const index = this.loyaltyTransactions.findIndex(item => item.id === id)
      if (index !== -1) this.loyaltyTransactions[index] = res.data.data
    },
    async deleteLoyaltyTransaction(id) {
      await deleteLoyaltyTransactionApi(id)
      this.loyaltyTransactions = this.loyaltyTransactions.filter(item => item.id !== id)
    },
  },
})