import http from './api'

export const getAllCashDrawersApi   = (filters) => http.get('/v1/cash-drawers', { params: filters })
export const getCashDrawerByIdApi   = (id)      => http.get(`/v1/cash-drawers/${id}`)
export const openCashDrawerApi      = (data)    => http.post('/v1/cash-drawers/open', data)
export const closeCashDrawerApi     = (id, data)=> http.patch(`/v1/cash-drawers/${id}/close`, data)
export const updateCashDrawerApi    = (id, data)=> http.put(`/v1/cash-drawers/${id}`, data)
export const deleteCashDrawerApi    = (id)      => http.delete(`/v1/cash-drawers/${id}`)

// Live reconciliation figures (opening float + cash sales + cash in - cash
// out - cash refunds) — readable any time during an open shift, not just
// at close.
export const getCashDrawerStatusApi = (id)      => http.get(`/v1/cash-drawers/${id}/status`)
export const cashInApi              = (id, data)=> http.post(`/v1/cash-drawers/${id}/cash-in`, data)
export const cashOutApi             = (id, data)=> http.post(`/v1/cash-drawers/${id}/cash-out`, data)
