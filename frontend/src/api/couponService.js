import http from './api'

export const getAllCouponsApi       = (filters) => http.get('/coupons', { params: filters })
export const getCouponByIdApi       = (id)      => http.get(`/coupons/${id}`)
export const createCouponApi        = (data)    => http.post('/coupons', data)
export const updateCouponApi        = (id, data)=> http.put(`/coupons/${id}`, data)
export const deleteCouponApi        = (id)      => http.delete(`/coupons/${id}`)