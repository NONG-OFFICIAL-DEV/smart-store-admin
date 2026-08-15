import http from './api'

export const getAllPermissionsApi = filters =>
  http.get('/v1/permissions', { params: filters })
export const getPermissionByIdApi = id => http.get(`/v1/permissions/${id}`)
export const createPermissionApi = data => http.post('/v1/permissions', data)
export const updatePermissionApi = (id, data) =>
  http.put(`/v1/permissions/${id}`, data)
export const deletePermissionApi = id => http.delete(`/v1/permissions/${id}`)
