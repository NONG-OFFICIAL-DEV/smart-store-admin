import http from './api'

export const getAllStaffApi = filters =>
  http.get('/v1/staff', { params: filters })
export const getStaffByIdApi = id => http.get(`/v1/staff/${id}`)
export const createStaffApi = data => http.post('/v1/staff', data)
export const updateStaffApi = (id, data) => http.put(`/v1/staff/${id}`, data)
export const deleteStaffApi = id => http.delete(`/v1/staff/${id}`)
