import http from './api'

export const getAllPurchaseOrdersApi = filters =>
  http.get('/v1/purchase-orders', { params: filters })
export const getPurchaseOrderByIdApi = id =>
  http.get(`/v1/purchase-orders/${id}`)
export const createPurchaseOrderApi = data =>
  http.post('/v1/purchase-orders', data)
export const updatePurchaseOrderApi = (id, data) =>
  http.put(`/v1/purchase-orders/${id}`, data)
export const deletePurchaseOrderApi = id =>
  http.delete(`/v1/purchase-orders/${id}`)
