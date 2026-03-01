import http from './api'

export const getAllPromotionsApi    = (filters) => http.get('/promotions', { params: filters })
export const getPromotionByIdApi    = (id)      => http.get(`/promotions/${id}`)
export const createPromotionApi     = (data)    => http.post('/promotions', data)
export const updatePromotionApi     = (id, data)=> http.put(`/promotions/${id}`, data)
export const deletePromotionApi     = (id)      => http.delete(`/promotions/${id}`)