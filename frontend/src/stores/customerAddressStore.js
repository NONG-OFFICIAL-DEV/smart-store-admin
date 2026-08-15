import { defineStore } from 'pinia'
import {
  getAllCustomerAddressesApi,
  getCustomerAddressByIdApi,
  createCustomerAddressApi,
  updateCustomerAddressApi,
  deleteCustomerAddressApi
} from '../api/customerAddressService'

export const useCustomerAddressStore = defineStore('customerAddress', {
  state: () => ({
    customerAddresses: [],
    customerAddress: null,
    pagination: {}
  }),

  actions: {
    // customerId required — addresses live under /customers/{id}/addresses
    async fetchCustomerAddresses(customerId, filters) {
      const res = await getAllCustomerAddressesApi(customerId, filters)
      this.customerAddresses = res.data.data
      this.pagination = res.data.meta
    },

    async fetchCustomerAddressById(id) {
      const res = await getCustomerAddressByIdApi(id)
      this.customerAddress = res.data.data
    },

    // customerId required — POST /customers/{id}/addresses
    async createCustomerAddress(customerId, data) {
      const res = await createCustomerAddressApi(customerId, data)
      this.customerAddresses.unshift(res.data.data)
    },

    // Shallow — PUT /addresses/{id}
    async updateCustomerAddress(id, data) {
      const res = await updateCustomerAddressApi(id, data)
      const index = this.customerAddresses.findIndex(item => item.id === id)
      if (index !== -1) this.customerAddresses[index] = res.data.data
    },

    // Shallow — DELETE /addresses/{id}
    async deleteCustomerAddress(id) {
      await deleteCustomerAddressApi(id)
      this.customerAddresses = this.customerAddresses.filter(
        item => item.id !== id
      )
    }
  }
})
