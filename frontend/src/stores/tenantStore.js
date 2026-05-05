import { defineStore } from 'pinia'
import {
  getAllTenantsApi,
  getTenantByIdApi,
  createTenantApi,
  updateTenantApi,
  deleteTenantApi,
  toggleTenantActiveApi,
  transferOwnershipApi,
  getBusinessTypesApi,
  getBranchTypeByBusinessTypeApi
} from '../api/tenantService'

export const useTenantStore = defineStore('tenant', {
  state: () => ({
    tenants: [],
    tenant: null,
    businessTypes: [],
    branchTypes: [],
    pagination: {}
  }),

  actions: {
    async fetchBusinessTypes() {
      const { data } = await getBusinessTypesApi()
      this.businessTypes = data.data
    },
    async fetchBranchTypeByBusinessType(id) {
      const { data } = await getBranchTypeByBusinessTypeApi(id)
      this.branchTypes = data.data
    },

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
      this.fetchTenants()
      return res
    },
    async updateTenant(id, data) {
      const res = await updateTenantApi(id, data)
      const index = this.tenants.findIndex(item => item.id === id)
      if (index !== -1) this.tenants[index] = res.data.data
      this.fetchTenants()
    },
    async deleteTenant(id) {
      await deleteTenantApi(id)
      this.tenants = this.tenants.filter(item => item.id !== id)
      this.fetchTenants()
    },
    // Toggle is_active — suspend or activate
    async toggleActive(id) {
      const res = await toggleTenantActiveApi(id)
      const index = this.tenants.findIndex(t => t.id === id)
      if (index !== -1) this.tenants[index] = res.data.data
    },

    // Transfer ownership to new owner by email
    async transferOwnership(id, newOwnerEmail) {
      const res = await transferOwnershipApi(id, {
        new_owner_email: newOwnerEmail
      })
      const index = this.tenants.findIndex(t => t.id === id)
      if (index !== -1) this.tenants[index] = res.data.data
    }
  }
})
