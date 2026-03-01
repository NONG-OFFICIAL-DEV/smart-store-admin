import http from './api'

export const getAllProductsApi = filters =>
  http.get('/v1/products', { params: filters })
export const getProductByIdApi = id => http.get(`/v1/products/${id}`)
export const createProductApi = data => http.post('/v1/products', data)
export const updateProductApi = (id, data) =>
  http.put(`/v1/products/${id}`, data)
export const deleteProductApi = id => http.delete(`/v1/products/${id}`)
