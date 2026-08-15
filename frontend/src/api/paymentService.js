import http from './api'

export const getAllPaymentsApi = filters =>
  http.get('/v1/payments', { params: filters })
export const getPaymentByIdApi = id => http.get(`/v1/payments/${id}`)
export const createPaymentApi = data => http.post('/v1/payments', data)
export const updatePaymentApi = (id, data) => http.put(`/v1/payments/${id}`, data)
export const deletePaymentApi = id => http.delete(`/v1/payments/${id}`)
