import http from './api'

export const getAllBranchHoursApi   = (filters) => http.get('/branch-hours', { params: filters })
export const getBranchHourByIdApi   = (id)      => http.get(`/branch-hours/${id}`)
export const createBranchHourApi    = (data)    => http.post('/branch-hours', data)
export const updateBranchHourApi    = (id, data)=> http.put(`/branch-hours/${id}`, data)
export const deleteBranchHourApi    = (id)      => http.delete(`/branch-hours/${id}`)