import http from './api'

export const getAllTenantsApi = filters =>
  http.get('/v1/tenants', { params: filters })
export const getTenantByIdApi = id => http.get(`/v1/tenants/${id}`)
export const getTenantToEditApi = id => http.get(`/v1/tenants/${id}/edit`)
export const createTenantApi = data => http.post('/v1/tenants', data)
export const updateTenantApi = (id, data) => http.put(`/v1/tenants/${id}`, data)
export const deleteTenantApi = id => http.delete(`/v1/tenants/${id}`)
export const toggleTenantActiveApi  = (id)       => http.post(`/v1/tenants/${id}/toggle-active`)       // ✅
export const resetOwnerPasswordApi  = (id)       => http.post(`/v1/tenants/${id}/reset-owner-password`)
export const getBusinessTypesApi   = () => http.get(`/v1/business-types`)
export const getBranchTypeByBusinessTypeApi   = (id) => http.get(`/v1/business-types/${id}/branch-types`)

// ── Manage Users (super-admin, scoped to one tenant) ────────────────────────
export const getAdminTenantUsersApi = tenantId => http.get(`/v1/tenants/${tenantId}/users`)
export const deactivateAdminTenantUserApi = (tenantId, userId) =>
  http.post(`/v1/tenants/${tenantId}/users/${userId}/deactivate`)
export const reactivateAdminTenantUserApi = (tenantId, userId) =>
  http.post(`/v1/tenants/${tenantId}/users/${userId}/reactivate`)
export const resetAdminTenantUserPasswordApi = (tenantId, userId) =>
  http.post(`/v1/tenants/${tenantId}/users/${userId}/reset-password`)