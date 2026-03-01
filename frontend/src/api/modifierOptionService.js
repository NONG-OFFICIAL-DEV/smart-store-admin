import http from './api'

export const getAllModifierOptionsApi = filters =>
  http.get('/modifier-options', { params: filters })
export const getModifierOptionByIdApi = id =>
  http.get(`/modifier-options/${id}`)
export const createModifierOptionApi = data =>
  http.post('/modifier-options', data)
export const updateModifierOptionApi = (id, data) =>
  http.put(`/modifier-options/${id}`, data)
export const deleteModifierOptionApi = id =>
  http.delete(`/modifier-options/${id}`)
