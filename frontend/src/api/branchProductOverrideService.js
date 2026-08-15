import http from './api'

export const getAllBranchProductOverridesApi = filters =>
  http.get('/branch-product-overrides', { params: filters })
export const getBranchProductOverrideByIdApi = id =>
  http.get(`/branch-product-overrides/${id}`)
export const createBranchProductOverrideApi = data =>
  http.post('/branch-product-overrides', data)
export const updateBranchProductOverrideApi = (id, data) =>
  http.put(`/branch-product-overrides/${id}`, data)
export const deleteBranchProductOverrideApi = id =>
  http.delete(`/branch-product-overrides/${id}`)
