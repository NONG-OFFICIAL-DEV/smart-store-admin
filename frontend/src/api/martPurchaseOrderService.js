// api/martPurchaseOrderService.js
import api from './api'

const base = 'v1/mart/purchase-orders'

export const getMartPurchaseOrdersApi  = (params)     => api.get(base, { params })
export const getMartPurchaseOrderApi   = (id)         => api.get(`${base}/${id}`)
export const createMartPurchaseOrderApi= (data)       => api.post(base, data)
export const updateMartPurchaseOrderApi= (id, data)   => api.put(`${base}/${id}`, data)
export const deleteMartPurchaseOrderApi= (id)         => api.delete(`${base}/${id}`)
export const receiveMartPurchaseOrderApi=(id, data)   => api.post(`${base}/${id}/receive`, data)
export const cancelMartPurchaseOrderApi= (id)         => api.post(`${base}/${id}/cancel`)