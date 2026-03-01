import http from './api'

export const getAllShiftsApi = filters =>
  http.get('/v1/shifts', { params: filters })
export const getShiftByIdApi = id => http.get(`/v1/shifts/${id}`)
export const createShiftApi = data => http.post('/v1/shifts', data)
export const updateShiftApi = (id, data) => http.put(`/v1/shifts/${id}`, data)
export const deleteShiftApi = id => http.delete(`/v1/shifts/${id}`)
