import http from './api'

export const getAllProductsApi = filters =>
  http.get('/v1/products', { params: filters })
export const getProductByIdApi = id => http.get(`/v1/products/${id}`)
export const createProductApi = formData =>
  http.post('/v1/products', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  })
export const updateProductApi = (id, formData) =>
  http.put(`/v1/products/${id}`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  })
export const deleteProductApi = id => http.delete(`/v1/products/${id}`)

export const attachModifierGroupsApi = (productId, modifierGroupIds) =>
  http.post(`/v1/products/${productId}/modifier-groups/sync`, {
    modifier_group_ids: modifierGroupIds
  })
