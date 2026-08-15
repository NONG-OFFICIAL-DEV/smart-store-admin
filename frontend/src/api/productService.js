import http from './api'

export const getAllProductsApi = filters =>
  http.get('/v1/products', { params: filters })
export const getProductByIdApi = id => http.get(`/v1/products/${id}`)
export const createProductApi = formData =>
  http.post('/v1/products', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  })
export const updateProductApi = (id, formData) => {
  // Convert boolean fields to 0/1 for multipart
  if (formData instanceof FormData) {
    ;['is_available', 'is_featured', 'track_stock'].forEach(field => {
      if (formData.has(field)) {
        const val = formData.get(field)
        formData.set(field, val === 'true' || val === true ? '1' : '0')
      }
    })
  }
  return http.put(`/v1/products/${id}`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  })
}
export const deleteProductApi = id => http.delete(`/v1/products/${id}`)

export const attachModifierGroupsApi = (productId, modifierGroupIds) =>
  http.post(`/v1/products/${productId}/modifier-groups/sync`, {
    modifier_group_ids: modifierGroupIds
  })


export const getProductForEditApi = id => http.get(`/v1/products/${id}`)

export const updateProductApiV2 = (id, data) => {
  if (data instanceof FormData) {
    return http.post(`/v1/products/${id}?_method=PUT`, data, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  }
  return http.put(`/v1/products/${id}`, data)
}