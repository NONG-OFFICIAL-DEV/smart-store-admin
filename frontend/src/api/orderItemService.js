import http from './api'

export const getAllOrderItemsApi    = (filters) => http.get('/order-items', { params: filters })
export const getOrderItemByIdApi    = (id)      => http.get(`/order-items/${id}`)
export const createOrderItemApi     = (data)    => http.post('/order-items', data)
export const updateOrderItemApi     = (id, data)=> http.put(`/order-items/${id}`, data)
export const deleteOrderItemApi     = (id)      => http.delete(`/order-items/${id}`)