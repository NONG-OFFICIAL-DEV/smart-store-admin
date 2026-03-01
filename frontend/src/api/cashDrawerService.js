import http from './api'

export const getAllCashDrawersApi   = (filters) => http.get('/cash-drawers', { params: filters })
export const getCashDrawerByIdApi   = (id)      => http.get(`/cash-drawers/${id}`)
export const createCashDrawerApi    = (data)    => http.post('/cash-drawers', data)
export const updateCashDrawerApi    = (id, data)=> http.put(`/cash-drawers/${id}`, data)
export const deleteCashDrawerApi    = (id)      => http.delete(`/cash-drawers/${id}`)