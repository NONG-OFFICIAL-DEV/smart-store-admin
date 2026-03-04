import http from './api'

export const getAllRefundsApi = filters =>
  http.get('/refunds', { params: filters })
export const getRefundByIdApi = id => http.get(`/refunds/${id}`)
export const createRefundApi = data => http.post('/refunds', data)
export const updateRefundApi = (id, data) => http.put(`/refunds/${id}`, data)
export const deleteRefundApi = id => http.delete(`/refunds/${id}`)
