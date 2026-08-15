// stores/martPurchaseOrderStore.js
import { defineStore } from 'pinia'
import {
  getMartPurchaseOrdersApi,
  getMartPurchaseOrderApi,
  createMartPurchaseOrderApi,
  updateMartPurchaseOrderApi,
  deleteMartPurchaseOrderApi,
  receiveMartPurchaseOrderApi,
  cancelMartPurchaseOrderApi
} from '@/api/martPurchaseOrderService'

export const useMartPurchaseOrderStore = defineStore('martPurchaseOrder', {
  state: () => ({
    orders: [],
    pagination: null,
    current: null,
    loading: false
  }),

  actions: {
    async fetchOrders(params = {}) {
      this.loading = true
      try {
        const res = await getMartPurchaseOrdersApi(params)
        this.orders = res.data.data
        this.pagination = res.data.meta
      } finally {
        this.loading = false
      }
    },

    async fetchOrder(id) {
      const res = await getMartPurchaseOrderApi(id)
      this.current = res.data.data
      return this.current
    },

    async createOrder(data) {
      const res = await createMartPurchaseOrderApi(data)
      return res.data.data
    },

    async updateOrder(id, data) {
      const res = await updateMartPurchaseOrderApi(id, data)
      return res.data.data
    },

    async receiveOrder(id, data) {
      const res = await receiveMartPurchaseOrderApi(id, data)
      this.current = res.data.data
      return res.data
    },

    async cancelOrder(id) {
      await cancelMartPurchaseOrderApi(id)
      const o = this.orders.find(o => o.id === id)
      if (o) o.status = 'cancelled'
    },

    async deleteOrder(id) {
      await deleteMartPurchaseOrderApi(id)
      this.orders = this.orders.filter(o => o.id !== id)
    }
  }
})
