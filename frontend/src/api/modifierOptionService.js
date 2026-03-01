import http from './api'

// List/Create → need groupId (nested)
export const getAllModifierOptionsApi = groupId =>
  http.get(`/v1/modifier-groups/${groupId}/options`)
export const createModifierOptionApi = data =>
  http.post(`/v1/modifier-groups/${data.group_id}/options`, data)

// Show/Update/Delete → shallow (no groupId needed)
export const getModifierOptionByIdApi = id =>
  http.get(`/v1/modifier-options/${id}`)
export const updateModifierOptionApi = (id, data) =>
  http.put(`/v1/modifier-options/${id}`, data)
export const deleteModifierOptionApi = id =>
  http.delete(`/v1/modifier-options/${id}`)
