import http from './api'

export const getAllFloorPlansApi    = (filters) => http.get('/floor-plans', { params: filters })
export const getFloorPlanByIdApi    = (id)      => http.get(`/floor-plans/${id}`)
export const createFloorPlanApi     = (data)    => http.post('/floor-plans', data)
export const updateFloorPlanApi     = (id, data)=> http.put(`/floor-plans/${id}`, data)
export const deleteFloorPlanApi     = (id)      => http.delete(`/floor-plans/${id}`)