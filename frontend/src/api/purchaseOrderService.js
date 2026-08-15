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
export const submitPurchaseOrderApi = id =>
  http.patch(`/v1/purchase-orders/${id}/submit`)
export const confirmPurchaseOrderApi = id =>
  http.patch(`/v1/purchase-orders/${id}/confirm`)
export const receivePurchaseOrderApi = (id, data) =>
  http.post(`/v1/purchase-orders/${id}/receive`, data)
export const cancelPurchaseOrderApi = id =>
  http.patch(`/v1/purchase-orders/${id}/cancel`)
