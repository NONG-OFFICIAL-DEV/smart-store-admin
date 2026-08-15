import http from './api'

export const getAllInventoryTransactionsApi = filters =>
  http.get('/inventory-transactions', { params: filters })
export const getInventoryTransactionByIdApi = id =>
  http.get(`/inventory-transactions/${id}`)
export const createInventoryTransactionApi = data =>
  http.post('/inventory-transactions', data)
export const updateInventoryTransactionApi = (id, data) =>
  http.put(`/inventory-transactions/${id}`, data)
export const deleteInventoryTransactionApi = id =>
  http.delete(`/inventory-transactions/${id}`)
