import http from './api'

export const getAllTablesApi = filters =>
  http.get('/v1/tables', { params: filters })
export const getTableByIdApi = id => http.get(`/v1/tables/${id}`)
export const createTableApi = data => http.post('/v1/tables', data)
export const updateTableApi = (id, data) => http.put(`/v1/tables/${id}`, data)
export const deleteTableApi = id => http.delete(`/v1/tables/${id}`)
