import http from './api'

export const getAllSuppliersApi     = (filters) => http.get('/v1/suppliers', { params: filters })
export const getSupplierByIdApi     = (id)      => http.get(`/v1/suppliers/${id}`)
export const createSupplierApi      = (data)    => http.post('/v1/suppliers', data)
export const updateSupplierApi      = (id, data)=> http.put(`/v1/suppliers/${id}`, data)
export const deleteSupplierApi      = (id)      => http.delete(`/v1/suppliers/${id}`)