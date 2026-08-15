import { defineStore } from 'pinia'
import {
  getAllSuppliersApi,
  getSupplierByIdApi,
  createSupplierApi,
  updateSupplierApi,
  deleteSupplierApi
} from '@/api/supplierService'

export const useSupplierStore = defineStore('supplier', {
  state: () => ({
    suppliers: { data: [], total: 0 }, // match v-data-table-server shape
    supplier: null,
    loading: false,
    error: null
  }),

  actions: {
    async fetchSuppliers(filters = {}) {
      this.loading = true
      try {
        const res = await getAllSuppliersApi(filters)
        this.suppliers = { ...res.data.meta, data: res.data.data }
      } catch (e) {
        this.error = e?.response?.data?.message ?? 'Failed to load suppliers'
      } finally {
        this.loading = false
      }
    },

    async fetchSupplierById(id) {
      const res = await getSupplierByIdApi(id)
      this.supplier = res.data.data
    },

    async addSupplier(data) {
      const res = await createSupplierApi(data)
      // Prepend to local list without refetching
      this.suppliers.data?.unshift(res.data.data)
      this.suppliers.total++
      return res.data.data
    },

    async updateSupplier(supplier) {
      // supplier is the full object with id
      const res = await updateSupplierApi(supplier.id, supplier)
      const index = this.suppliers.data?.findIndex(s => s.id === supplier.id)
      if (index !== -1) this.suppliers.data[index] = res.data.data
      return res.data.data
    },

    async removeSupplier(id) {
      await deleteSupplierApi(id)
      this.suppliers.data = this.suppliers.data?.filter(s => s.id !== id)
      this.suppliers.total = Math.max(0, (this.suppliers.total ?? 1) - 1)
    }
  }
})
