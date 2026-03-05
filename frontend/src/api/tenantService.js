import http from './api'

export const getAllTenantsApi = filters =>
  http.get('/v1/tenants', { params: filters })
export const getTenantByIdApi = id => http.get(`/v1/tenants/${id}`)
export const createTenantApi = data => http.post('/v1/tenants', data)
export const updateTenantApi = (id, data) => http.put(`/v1/tenants/${id}`, data)
export const deleteTenantApi = id => http.delete(`/v1/tenants/${id}`)
export const toggleTenantActiveApi  = (id)       => api.post(`/v1/tenants/${id}/toggle-active`)       // ✅
export const transferOwnershipApi   = (id, data) => api.post(`/v1/tenants/${id}/transfer-ownership`, data) 