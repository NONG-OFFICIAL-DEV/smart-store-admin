import { defineStore } from 'pinia'
import { getAllOrdersApi, getOrderByIdApi, createOrderApi, updateOrderApi, deleteOrderApi } from '../api/orderService'

export const useOrderStore = defineStore('order', {
  state: () => ({
    orders:     [],
    order:      null,
    pagination: {},
  }),

  actions: {
    async fetchOrders(filters) {
      const res       = await getAllOrdersApi(filters)
      this.orders     = res.data.data.data
      this.pagination = res.data.data
    },
    async fetchOrderById(id) {
      const res   = await getOrderByIdApi(id)
      this.order  = res.data.data
    },
    async createOrder(data) {
      const res = await createOrderApi(data)
      this.orders.unshift(res.data.data)
    },
    async updateOrder(id, data) {
      const res   = await updateOrderApi(id, data)
      const index = this.orders.findIndex(item => item.id === id)
      if (index !== -1) this.orders[index] = res.data.data
    },
    async deleteOrder(id) {
      await deleteOrderApi(id)
      this.orders = this.orders.filter(item => item.id !== id)
    },
  },
})