import http from './api'

export const getAllPromotionsApi    = (filters) => http.get('/v1/promotions', { params: filters })
export const getPromotionByIdApi    = (id)      => http.get(`/v1/promotions/${id}`)
export const createPromotionApi     = (data)    => http.post('/v1/promotions', data)
export const updatePromotionApi     = (id, data)=> http.put(`/v1/promotions/${id}`, data)
export const deletePromotionApi     = (id)      => http.delete(`/v1/promotions/${id}`)