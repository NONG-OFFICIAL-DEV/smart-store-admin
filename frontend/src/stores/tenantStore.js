import { defineStore } from 'pinia'
import {
  getAllTenantsApi,
  getTenantByIdApi,
  getTenantToEditApi,
  createTenantApi,
  updateTenantApi,
  deleteTenantApi,
  toggleTenantActiveApi,
  resetOwnerPasswordApi,
  getBusinessTypesApi,
  getBranchTypeByBusinessTypeApi
} from '../api/tenantService'

export const useTenantStore = defineStore('tenant', {
  state: () => ({
    tenants: [],
    tenant: null,
    businessTypes: [],
    branchTypes: [],
    tenantDetail: null, 
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
      this.tenants = res.data.data
      this.pagination = res.data.meta
    },
    async fetchTenantById(id) {
      const res = await getTenantByIdApi(id)
      this.tenant = res.data.data
      this.tenantDetail = res.data.data
      return res.data.data
    },
    async fetchTenantForEdit(id) {
      const { data } = await getTenantToEditApi(id)
      return data.data // returns the flat shape populateForm expects
    },
    async createTenant(data) {
      const res = await createTenantApi(data)
      this.fetchTenants()
      return res
    },
    async updateTenant(id, data) {
      await updateTenantApi(id, data)
    },
    async deleteTenant(id) {
      await deleteTenantApi(id)
      this.tenants = this.tenants.filter(item => item.id !== id)
      this.fetchTenants()
    },
    // Toggle is_active — suspend or activate
    async toggleTenantActive(id) {
      const res = await toggleTenantActiveApi(id)
      const index = this.tenants.findIndex(t => t.id === id)
      if (index !== -1) this.tenants[index] = res.data.data
    },

    async resetOwnerPassword(id) {
      const res = await resetOwnerPasswordApi(id)
      return res.data.data?.temporary_password
    },

    async generatePaymentQR() {
      // TODO: implement API call to generate QR code for payment
    }
  }
})
