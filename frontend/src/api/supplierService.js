import http from './api'

export const getAllSuppliersApi     = (filters) => http.get('/suppliers', { params: filters })
export const getSupplierByIdApi     = (id)      => http.get(`/suppliers/${id}`)
export const createSupplierApi      = (data)    => http.post('/suppliers', data)
export const updateSupplierApi      = (id, data)=> http.put(`/suppliers/${id}`, data)
export const deleteSupplierApi      = (id)      => http.delete(`/suppliers/${id}`)