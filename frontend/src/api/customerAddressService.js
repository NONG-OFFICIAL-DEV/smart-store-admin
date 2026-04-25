import http from './api'

// Addresses are nested under customers: /v1/customers/{customerId}/addresses
// Shallow routes allow update/delete directly on /v1/addresses/{id}

export const getAllCustomerAddressesApi = (customerId, filters) =>
  http.get(`/v1/customers/${customerId}/addresses`, { params: filters })

export const getCustomerAddressByIdApi = (customerId, id) =>
  http.get(`/v1/customers/${customerId}/addresses/${id}`)

export const createCustomerAddressApi = (customerId, data) =>
  http.post(`/v1/customers/${customerId}/addresses`, data)

// Shallow — update & delete go direct to /addresses/{id}
export const updateCustomerAddressApi = (id, data) =>
  http.put(`/v1/addresses/${id}`, data)

export const deleteCustomerAddressApi = (id) =>
  http.delete(`/v1/addresses/${id}`)