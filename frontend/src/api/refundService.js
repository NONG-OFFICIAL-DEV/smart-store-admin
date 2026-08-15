import http from './api'

// Refunds are read-only + creatable-via-payment — there is no
// update/delete route on the backend (refunds are immutable financial
// records once created; only index/show/store exist, and store is only
// reachable nested under a specific payment).
export const getAllRefundsApi = filters =>
  http.get('/v1/refunds', { params: filters })
export const getRefundByIdApi = id => http.get(`/v1/refunds/${id}`)
export const createRefundApi = (paymentId, data) =>
  http.post(`/v1/payments/${paymentId}/refund`, data)
