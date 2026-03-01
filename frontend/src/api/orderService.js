import http from './api'

export const getAllOrdersApi        = (filters) => http.get('/orders', { params: filters })
export const getOrderByIdApi        = (id)      => http.get(`/orders/${id}`)
export const createOrderApi         = (data)    => http.post('/orders', data)
export const updateOrderApi         = (id, data)=> http.put(`/orders/${id}`, data)
export const deleteOrderApi         = (id)      => http.delete(`/orders/${id}`)