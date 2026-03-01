import http from './api'

export const getAllInventoryStocksApi  = (filters) => http.get('/inventory-stock', { params: filters })
export const getInventoryStockByIdApi  = (id)      => http.get(`/inventory-stock/${id}`)
export const createInventoryStockApi   = (data)    => http.post('/inventory-stock', data)
export const updateInventoryStockApi   = (id, data)=> http.put(`/inventory-stock/${id}`, data)
export const deleteInventoryStockApi   = (id)      => http.delete(`/inventory-stock/${id}`)