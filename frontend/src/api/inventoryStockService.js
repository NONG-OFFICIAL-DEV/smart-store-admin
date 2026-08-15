import http from './api'

export const getAllInventoryStocksApi  = (filters) => http.get('/v1/inventory-stock', { params: filters })
export const getInventoryStockByIdApi  = (id)      => http.get(`/v1/inventory-stock/${id}`)
export const createInventoryStockApi   = (data)    => http.post('/v1/inventory-stock', data)
export const updateInventoryStockApi   = (id, data)=> http.put(`/v1/inventory-stock/${id}`, data)
export const deleteInventoryStockApi   = (id)      => http.delete(`/v1/inventory-stock/${id}`)