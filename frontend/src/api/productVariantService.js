import http from './api'

export const getAllProductVariantsApi = filters =>
  http.get('/v1/product-variants', { params: filters })
export const getProductVariantByIdApi = id =>
  http.get(`/v1/product-variants/${id}`)
export const createProductVariantApi = data =>
  http.post('/v1/product-variants', data)
export const updateProductVariantApi = (id, data) =>
  http.put(`/v1/product-variants/${id}`, data)
export const deleteProductVariantApi = id =>
  http.delete(`/v1/product-variants/${id}`)
