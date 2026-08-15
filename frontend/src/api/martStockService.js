// api/martStockService.js
import api from './api'

export const adjustStockApi    = (data)   => api.post('v1/mart/stock/adjust', data)
export const getMovementsApi   = (params) => api.get('v1/mart/stock/movements', { params })
export const getLowStockApi    = (params) => api.get('v1/mart/stock/low-stock', { params })