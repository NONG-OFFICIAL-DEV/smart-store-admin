import { defineStore } from 'pinia'
import { getAllPurchaseOrdersApi, getPurchaseOrderByIdApi, createPurchaseOrderApi, updatePurchaseOrderApi, deletePurchaseOrderApi } from '../api/purchaseOrderService'

export const usePurchaseOrderStore = defineStore('purchaseOrder', {
  state: () => ({
    purchaseOrders: [],
    purchaseOrder:  null,
    pagination:     {},
  }),

  actions: {
    async fetchPurchaseOrders(filters) {
      const res             = await getAllPurchaseOrdersApi(filters)
      this.purchaseOrders   = res.data.data.data
      this.pagination       = res.data.data
    },
    async fetchPurchaseOrderById(id) {
      const res            = await getPurchaseOrderByIdApi(id)
      this.purchaseOrder   = res.data.data
    },
    async createPurchaseOrder(data) {
      const res = await createPurchaseOrderApi(data)
      this.purchaseOrders.unshift(res.data.data)
    },
    async updatePurchaseOrder(id, data) {
      const res   = await updatePurchaseOrderApi(id, data)
      const index = this.purchaseOrders.findIndex(item => item.id === id)
      if (index !== -1) this.purchaseOrders[index] = res.data.data
    },
    async deletePurchaseOrder(id) {
      await deletePurchaseOrderApi(id)
      this.purchaseOrders = this.purchaseOrders.filter(item => item.id !== id)
    },
  },
})