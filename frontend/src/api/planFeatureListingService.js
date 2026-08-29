import http from './api'

export const getAllPlanFeatureListingsApi = () => http.get('/v1/plan-feature-listings')
export const createPlanFeatureListingApi = data => http.post('/v1/plan-feature-listings', data)
export const updatePlanFeatureListingApi = (id, data) => http.put(`/v1/plan-feature-listings/${id}`, data)
export const deletePlanFeatureListingApi = id => http.delete(`/v1/plan-feature-listings/${id}`)
