import http from './api'

export const getAllCustomerAddressesApi  = (filters) => http.get('/customer-addresses', { params: filters })
export const getCustomerAddressByIdApi   = (id)      => http.get(`/customer-addresses/${id}`)
export const createCustomerAddressApi    = (data)    => http.post('/customer-addresses', data)
export const updateCustomerAddressApi    = (id, data)=> http.put(`/customer-addresses/${id}`, data)
export const deleteCustomerAddressApi    = (id)      => http.delete(`/customer-addresses/${id}`)