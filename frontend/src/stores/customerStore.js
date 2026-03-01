import { defineStore } from 'pinia'
import { getAllCustomersApi, getCustomerByIdApi, createCustomerApi, updateCustomerApi, deleteCustomerApi } from '../api/customerService'

export const useCustomerStore = defineStore('customer', {
  state: () => ({
    customers:  [],
    customer:   null,
    pagination: {},
  }),

  actions: {
    async fetchCustomers(filters) {
      const res         = await getAllCustomersApi(filters)
      this.customers    = res.data.data.data
      this.pagination   = res.data.data
    },
    async fetchCustomerById(id) {
      const res       = await getCustomerByIdApi(id)
      this.customer   = res.data.data
    },
    async createCustomer(data) {
      const res = await createCustomerApi(data)
      this.customers.unshift(res.data.data)
    },
    async updateCustomer(id, data) {
      const res   = await updateCustomerApi(id, data)
      const index = this.customers.findIndex(item => item.id === id)
      if (index !== -1) this.customers[index] = res.data.data
    },
    async deleteCustomer(id) {
      await deleteCustomerApi(id)
      this.customers = this.customers.filter(item => item.id !== id)
    },
  },
})