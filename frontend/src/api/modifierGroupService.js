import http from './api'

export const getAllModifierGroupsApi = filters =>
  http.get('/v1/modifier-groups', { params: filters })
export const getModifierGroupByIdApi = id =>
  http.get(`/v1/modifier-groups/${id}`)
export const createModifierGroupApi = data =>
  http.post('/v1/modifier-groups', data)
export const updateModifierGroupApi = (id, data) =>
  http.put(`/v1/modifier-groups/${id}`, data)
export const deleteModifierGroupApi = id =>
  http.delete(`/v1/modifier-groups/${id}`)
