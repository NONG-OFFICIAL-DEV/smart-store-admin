import http from './api'

export const getAllTablesApi        = (filters) => http.get('/tables', { params: filters })
export const getTableByIdApi        = (id)      => http.get(`/tables/${id}`)
export const createTableApi         = (data)    => http.post('/tables', data)
export const updateTableApi         = (id, data)=> http.put(`/tables/${id}`, data)
export const deleteTableApi         = (id)      => http.delete(`/tables/${id}`)