import http from './api'

export const getAllLoyaltyRewardsApi = filters => http.get('/v1/loyalty-rewards', { params: filters })
export const getLoyaltyRewardByIdApi = id => http.get(`/v1/loyalty-rewards/${id}`)
export const createLoyaltyRewardApi = data => http.post('/v1/loyalty-rewards', data)
export const updateLoyaltyRewardApi = (id, data) => http.put(`/v1/loyalty-rewards/${id}`, data)
export const deleteLoyaltyRewardApi = id => http.delete(`/v1/loyalty-rewards/${id}`)
