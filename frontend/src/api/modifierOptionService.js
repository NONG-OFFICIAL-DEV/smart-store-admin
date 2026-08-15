import http from './api'

// All actions are nested under the group — ->shallow() doesn't actually
// take effect here because the resource is wrapped in Route::prefix()
// ->group() rather than Laravel's dot-nested resource syntax, so every
// route (including show/update/delete) still requires {modifierGroup}.
export const getAllModifierOptionsApi = groupId =>
  http.get(`/v1/modifier-groups/${groupId}/options`)
export const createModifierOptionApi = data =>
  http.post(`/v1/modifier-groups/${data.group_id}/options`, data)
export const getModifierOptionByIdApi = (groupId, id) =>
  http.get(`/v1/modifier-groups/${groupId}/options/${id}`)
export const updateModifierOptionApi = (groupId, id, data) =>
  http.put(`/v1/modifier-groups/${groupId}/options/${id}`, data)
export const deleteModifierOptionApi = (groupId, id) =>
  http.delete(`/v1/modifier-groups/${groupId}/options/${id}`)
