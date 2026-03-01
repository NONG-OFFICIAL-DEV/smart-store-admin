import http from './api'

export const getAllBranchMenusApi = filters =>
  http.get('/v1/branch-menus', { params: filters })
export const getBranchMenuByIdApi = id => http.get(`/v1/branch-menus/${id}`)
export const createBranchMenuApi = data => http.post('/v1/branch-menus', data)
export const updateBranchMenuApi = (id, data) =>
  http.put(`/v1/branch-menus/${id}`, data)
export const deleteBranchMenuApi = id => http.delete(`/v1/branch-menus/${id}`)
export const unassignBranchMenuApi = data =>
  http.delete('/v1/branch-menus/unassign', { data })
export const getAvailableMenusNowApi = branchId =>
  http.get(`/v1/branch-menus/branch/${branchId}/available-now`)
