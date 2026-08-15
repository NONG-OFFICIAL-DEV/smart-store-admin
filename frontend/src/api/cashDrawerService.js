import http from './api'

export const getAllCashDrawersApi   = (filters) => http.get('/v1/cash-drawers', { params: filters })
export const getCashDrawerByIdApi   = (id)      => http.get(`/v1/cash-drawers/${id}`)
export const openCashDrawerApi      = (data)    => http.post('/v1/cash-drawers/open', data)
export const closeCashDrawerApi     = (id, data)=> http.patch(`/v1/cash-drawers/${id}/close`, data)
export const updateCashDrawerApi    = (id, data)=> http.put(`/v1/cash-drawers/${id}`, data)
export const deleteCashDrawerApi    = (id)      => http.delete(`/v1/cash-drawers/${id}`)