import { defineStore } from 'pinia'
import {
  getAllOrdersApi,
  getOrderByIdApi,
  createOrderApi,
  updateOrderApi,
  deleteOrderApi,
  exportOrdersApi,
  getAllOrdersReportApi
} from '../api/orderService'

export const useOrderStore = defineStore('order', {
  state: () => ({
    orders: [],
    order: null,
    pagination: {},
    // Active filters
    filters: {
      search: '',
      branch_id: null,
      status: null,
      order_type: null,
      payment_method: null,
      date_from: null,
      date_to: null,
      per_page: 15,
      page: 1
    }
  }),

  actions: {
    async getAllOrdersReport(filters) {
      const res = await getAllOrdersReportApi(filters)
      return res
    },
    async fetchOrders(filters) {
      const res = await getAllOrdersApi(filters)
      this.orders = res.data.data.data
      this.pagination = res.data.data
    },
    async fetchOrderById(id) {
      const res = await getOrderByIdApi(id)
      this.order = res.data.data
    },
    async createOrder(data) {
      const res = await createOrderApi(data)
      this.orders.unshift(res.data.data)
    },
    async updateOrder(id, data) {
      const res = await updateOrderApi(id, data)
      const index = this.orders.findIndex(item => item.id === id)
      if (index !== -1) this.orders[index] = res.data.data
    },
    async deleteOrder(id) {
      await deleteOrderApi(id)
      this.orders = this.orders.filter(item => item.id !== id)
    },
    async exportOrders(param) {
      const res = await exportOrdersApi(param)
      return res
    },
    resetFilters() {
      this.filters = {
        search: '',
        branch_id: null,
        status: null,
        order_type: null,
        payment_method: null,
        date_from: null,
        date_to: null,
        per_page: 15,
        page: 1
      }
    }
  }
})
