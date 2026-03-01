import { defineStore } from 'pinia'
import { getAllCustomerAddressesApi, getCustomerAddressByIdApi, createCustomerAddressApi, updateCustomerAddressApi, deleteCustomerAddressApi } from '../api/customerAddressService'

export const useCustomerAddressStore = defineStore('customerAddress', {
  state: () => ({
    customerAddresses: [],
    customerAddress:   null,
    pagination:        {},
  }),

  actions: {
    async fetchCustomerAddresses(filters) {
      const res                  = await getAllCustomerAddressesApi(filters)
      this.customerAddresses     = res.data.data.data
      this.pagination            = res.data.data
    },
    async fetchCustomerAddressById(id) {
      const res                 = await getCustomerAddressByIdApi(id)
      this.customerAddress      = res.data.data
    },
    async createCustomerAddress(data) {
      const res = await createCustomerAddressApi(data)
      this.customerAddresses.unshift(res.data.data)
    },
    async updateCustomerAddress(id, data) {
      const res   = await updateCustomerAddressApi(id, data)
      const index = this.customerAddresses.findIndex(item => item.id === id)
      if (index !== -1) this.customerAddresses[index] = res.data.data
    },
    async deleteCustomerAddress(id) {
      await deleteCustomerAddressApi(id)
      this.customerAddresses = this.customerAddresses.filter(item => item.id !== id)
    },
  },
})