import { defineStore } from 'pinia'
import { getAllOrderItemsApi, getOrderItemByIdApi, createOrderItemApi, updateOrderItemApi, deleteOrderItemApi } from '../api/orderItemService'

export const useOrderItemStore = defineStore('orderItem', {
  state: () => ({
    orderItems:  [],
    orderItem:   null,
    pagination:  {},
  }),

  actions: {
    async fetchOrderItems(filters) {
      const res          = await getAllOrderItemsApi(filters)
      this.orderItems    = res.data.data.data
      this.pagination    = res.data.data
    },
    async fetchOrderItemById(id) {
      const res        = await getOrderItemByIdApi(id)
      this.orderItem   = res.data.data
    },
    async createOrderItem(data) {
      const res = await createOrderItemApi(data)
      this.orderItems.unshift(res.data.data)
    },
    async updateOrderItem(id, data) {
      const res   = await updateOrderItemApi(id, data)
      const index = this.orderItems.findIndex(item => item.id === id)
      if (index !== -1) this.orderItems[index] = res.data.data
    },
    async deleteOrderItem(id) {
      await deleteOrderItemApi(id)
      this.orderItems = this.orderItems.filter(item => item.id !== id)
    },
  },
})