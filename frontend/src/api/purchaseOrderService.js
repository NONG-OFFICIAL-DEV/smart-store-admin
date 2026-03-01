import http from './api'

export const getAllPurchaseOrdersApi = filters =>
  http.get('/purchase-orders', { params: filters })
export const getPurchaseOrderByIdApi = id => http.get(`/purchase-orders/${id}`)
export const createPurchaseOrderApi = data =>
  http.post('/purchase-orders', data)
export const updatePurchaseOrderApi = (id, data) =>
  http.put(`/purchase-orders/${id}`, data)
export const deletePurchaseOrderApi = id =>
  http.delete(`/purchase-orders/${id}`)
