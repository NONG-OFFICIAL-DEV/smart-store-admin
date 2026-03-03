import { defineStore } from 'pinia'
import {
  getAllInventoryTransactionsApi,
  getInventoryTransactionByIdApi,
  createInventoryTransactionApi,
  updateInventoryTransactionApi,
  deleteInventoryTransactionApi
} from '../api/inventoryTransactionService'

export const useInventoryTransactionStore = defineStore(
  'inventoryTransaction',
  {
    state: () => ({
      inventoryTransactions: [],
      inventoryTransaction: null,
      pagination: {}
    }),

    actions: {
      async fetchInventoryTransactions(filters) {
        const res = await getAllInventoryTransactionsApi(filters)
        this.inventoryTransactions = res.data.data.data
        this.pagination = res.data.data
      },
      async fetchInventoryTransactionById(id) {
        const res = await getInventoryTransactionByIdApi(id)
        this.inventoryTransaction = res.data.data
      },
      async createInventoryTransaction(data) {
        const res = await createInventoryTransactionApi(data)
        this.inventoryTransactions.unshift(res.data.data)
      },
      async updateInventoryTransaction(id, data) {
        const res = await updateInventoryTransactionApi(id, data)
        const index = this.inventoryTransactions.findIndex(
          item => item.id === id
        )
        if (index !== -1) this.inventoryTransactions[index] = res.data.data
      },
      async deleteInventoryTransaction(id) {
        await deleteInventoryTransactionApi(id)
        this.inventoryTransactions = this.inventoryTransactions.filter(
          item => item.id !== id
        )
      }
    }
  }
)
