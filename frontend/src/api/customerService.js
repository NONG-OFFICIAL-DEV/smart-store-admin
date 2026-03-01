import http from './api'

export const getAllCustomersApi     = (filters) => http.get('/customers', { params: filters })
export const getCustomerByIdApi     = (id)      => http.get(`/customers/${id}`)
export const createCustomerApi      = (data)    => http.post('/customers', data)
export const updateCustomerApi      = (id, data)=> http.put(`/customers/${id}`, data)
export const deleteCustomerApi      = (id)      => http.delete(`/customers/${id}`)