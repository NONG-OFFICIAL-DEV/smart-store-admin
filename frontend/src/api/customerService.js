import http from './api'

export const getAllCustomersApi     = (filters) => http.get('/v1/customers', { params: filters })
export const getCustomerByIdApi     = (id)      => http.get(`/v1/customers/${id}`)
export const createCustomerApi      = (data)    => http.post('/v1/customers', data)
export const updateCustomerApi      = (id, data)=> http.put(`/v1/customers/${id}`, data)
export const deleteCustomerApi      = (id)      => http.delete(`/v1/customers/${id}`)