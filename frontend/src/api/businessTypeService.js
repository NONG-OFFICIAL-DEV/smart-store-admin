import http from './api'

export const getAllBusinessTypesApi  = (filters) => http.get('/v1/business-types', { params: filters })
export const getBusinessTypeApi      = (id)      => http.get(`/v1/business-types/${id}`)
export const createBusinessTypeApi   = (data)    => http.post('/v1/business-types', data)
export const updateBusinessTypeApi   = (id, data) => http.put(`/v1/business-types/${id}`, data)
export const deleteBusinessTypeApi   = (id)      => http.delete(`/v1/business-types/${id}`)