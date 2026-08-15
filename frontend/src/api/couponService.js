import http from './api'

export const getAllCouponsApi       = (filters) => http.get('/v1/coupons', { params: filters })
export const getCouponByIdApi       = (id)      => http.get(`/v1/coupons/${id}`)
export const createCouponApi        = (data)    => http.post('/v1/coupons', data)
export const updateCouponApi        = (id, data)=> http.put(`/v1/coupons/${id}`, data)
export const deleteCouponApi        = (id)      => http.delete(`/v1/coupons/${id}`)