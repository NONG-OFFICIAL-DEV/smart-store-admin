import http from './api'

export const getAllOrdersApi = filters =>
  http.get('/v1/orders', { params: filters })
export const getAllOrdersReportApi = filters =>
  http.get('/v1/orders/report', { params: filters })
export const getOrderByIdApi = id => http.get(`/v1/orders/${id}`)
export const createOrderApi = data => http.post('/v1/orders', data)
export const updateOrderApi = (id, data) => http.put(`/v1/orders/${id}`, data)
export const deleteOrderApi = id => http.delete(`/v1/orders/${id}`)
export const exportOrdersApi = (params = {}) => {
  return http.get('/v1/orders/export', {
    params,
    responseType: 'blob',
  })
}