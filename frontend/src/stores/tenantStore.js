import { defineStore } from 'pinia'
import {
  getAllTenantsApi,
  getTenantByIdApi,
  createTenantApi,
  updateTenantApi,
  deleteTenantApi
} from '../api/tenantService'

export const useTenantStore = defineStore('tenant', {
  state: () => ({
    tenants: [],
    tenant: null,
    pagination: {}
  }),

  actions: {
    async fetchTenants(filters) {
      const res = await getAllTenantsApi(filters)
      this.tenants = res.data.data.data
      this.pagination = res.data.data
    },
    async fetchTenantById(id) {
      const res = await getTenantByIdApi(id)
      this.tenant = res.data.data
    },
    async createTenant(data) {
      const res = await createTenantApi(data)
      return res
    },
    async updateTenant(id, data) {
      const res = await updateTenantApi(id, data)
      const index = this.tenants.findIndex(item => item.id === id)
      if (index !== -1) this.tenants[index] = res.data.data
    },
    async deleteTenant(id) {
      await deleteTenantApi(id)
      this.tenants = this.tenants.filter(item => item.id !== id)
    }
  }
})
