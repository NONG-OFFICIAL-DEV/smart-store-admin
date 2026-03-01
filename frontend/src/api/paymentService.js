import http from './api'

export const getAllPaymentsApi = filters =>
  http.get('/payments', { params: filters })
export const getPaymentByIdApi = id => http.get(`/payments/${id}`)
export const createPaymentApi = data => http.post('/payments', data)
export const updatePaymentApi = (id, data) => http.put(`/payments/${id}`, data)
export const deletePaymentApi = id => http.delete(`/payments/${id}`)
