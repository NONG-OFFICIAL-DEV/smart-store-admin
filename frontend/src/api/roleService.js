import http from './api'

export const getAllRolesApi = filters =>
  http.get('/v1/roles', { params: filters })
export const getRoleByIdApi = id => http.get(`/v1/roles/${id}`)
export const createRoleApi = data => http.post('/v1/roles', data)
export const updateRoleApi = (id, data) => http.put(`/v1/roles/${id}`, data)
export const deleteRoleApi = id => http.delete(`/v1/roles/${id}`)
