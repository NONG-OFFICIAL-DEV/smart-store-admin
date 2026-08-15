import http from './api'

export const getAllBusinessTypesApi  = (filters) => http.get('/v1/business-types', { params: filters })
// Unauthenticated — used by the public signup form (Register.vue), hits
// the same controller via the separate v1/public/business-types route.
export const getPublicBusinessTypesApi = () => http.get('/v1/public/business-types')
export const getBusinessTypeApi      = (id)      => http.get(`/v1/business-types/${id}`)
export const createBusinessTypeApi   = (data)    => http.post('/v1/business-types', data)
export const updateBusinessTypeApi   = (id, data) => http.put(`/v1/business-types/${id}`, data)
export const deleteBusinessTypeApi   = (id)      => http.delete(`/v1/business-types/${id}`)