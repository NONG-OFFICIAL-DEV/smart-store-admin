import { defineStore } from 'pinia'
import {
  getAllSuppliersApi,
  getSupplierByIdApi,
  createSupplierApi,
  updateSupplierApi,
  deleteSupplierApi
} from '../api/supplierService'

export const useSupplierStore = defineStore('supplier', {
  state: () => ({
    suppliers: [],
    supplier: null,
    pagination: {}
  }),

  actions: {
    async fetchSuppliers(filters) {
      const res = await getAllSuppliersApi(filters)
      this.suppliers = res.data.data.data
      this.pagination = res.data.data
    },
    async fetchSupplierById(id) {
      const res = await getSupplierByIdApi(id)
      this.supplier = res.data.data
    },
    async createSupplier(data) {
      const res = await createSupplierApi(data)
      this.suppliers.unshift(res.data.data)
    },
    async updateSupplier(id, data) {
      const res = await updateSupplierApi(id, data)
      const index = this.suppliers.findIndex(item => item.id === id)
      if (index !== -1) this.suppliers[index] = res.data.data
    },
    async deleteSupplier(id) {
      await deleteSupplierApi(id)
      this.suppliers = this.suppliers.filter(item => item.id !== id)
    }
  }
})
