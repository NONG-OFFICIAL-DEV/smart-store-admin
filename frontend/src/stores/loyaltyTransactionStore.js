import { defineStore } from 'pinia'
import { getAllLoyaltyTransactionsApi, getLoyaltyTransactionByIdApi, getLoyaltyTransactionsByCustomerApi } from '../api/loyaltyTransactionService'

export const useLoyaltyTransactionStore = defineStore('loyaltyTransaction', {
  state: () => ({
    loyaltyTransactions: [],
    loyaltyTransaction:  null,
    pagination:          {},
  }),

  actions: {
    async fetchLoyaltyTransactions(filters) {
      const res                    = await getAllLoyaltyTransactionsApi(filters)
      this.loyaltyTransactions     = res.data.data
      this.pagination              = res.data.meta
    },
    async fetchLoyaltyTransactionById(id) {
      const res                   = await getLoyaltyTransactionByIdApi(id)
      this.loyaltyTransaction     = res.data.data
    },
    async fetchLoyaltyTransactionsByCustomer(customerId, filters) {
      const res                    = await getLoyaltyTransactionsByCustomerApi(customerId, filters)
      this.loyaltyTransactions     = res.data.data
      this.pagination              = res.data.meta
    },
  },
})
