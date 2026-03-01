import http from './api'

export const getAllBranchesApi = filters =>
  http.get('/v1/branches', { params: filters })
export const getBranchByIdApi = id => http.get(`/v1/branches/${id}`)
export const createBranchApi = data => http.post('/v1/branches', data)
export const updateBranchApi = (id, data) => http.put(`/v1/branches/${id}`, data)
export const deleteBranchApi = id => http.delete(`/v1/branches/${id}`)
