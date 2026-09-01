import http from './api'

export const getAllFloorPlansApi    = (filters) => http.get('/v1/floor-plans', { params: filters })
export const getFloorPlanByIdApi    = (id)      => http.get(`/v1/floor-plans/${id}`)
export const createFloorPlanApi     = (data)    => http.post('/v1/floor-plans', data)
export const updateFloorPlanApi     = (id, data)=> http.put(`/v1/floor-plans/${id}`, data)
export const deleteFloorPlanApi     = (id)      => http.delete(`/v1/floor-plans/${id}`)