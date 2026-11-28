import http from './api'

export const getAllCashRegistersApi = (filters) => http.get('/v1/cash-registers', { params: filters })
export const getCashRegisterByIdApi = (id)      => http.get(`/v1/cash-registers/${id}`)
export const createCashRegisterApi  = (data)    => http.post('/v1/cash-registers', data)
export const updateCashRegisterApi  = (id, data)=> http.put(`/v1/cash-registers/${id}`, data)
export const deleteCashRegisterApi  = (id)      => http.delete(`/v1/cash-registers/${id}`)
