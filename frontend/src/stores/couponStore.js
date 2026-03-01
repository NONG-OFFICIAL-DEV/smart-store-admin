import { defineStore } from 'pinia'
import { getAllCouponsApi, getCouponByIdApi, createCouponApi, updateCouponApi, deleteCouponApi } from '../api/couponService'

export const useCouponStore = defineStore('coupon', {
  state: () => ({
    coupons:    [],
    coupon:     null,
    pagination: {},
  }),

  actions: {
    async fetchCoupons(filters) {
      const res       = await getAllCouponsApi(filters)
      this.coupons    = res.data.data.data
      this.pagination = res.data.data
    },
    async fetchCouponById(id) {
      const res     = await getCouponByIdApi(id)
      this.coupon   = res.data.data
    },
    async createCoupon(data) {
      const res = await createCouponApi(data)
      this.coupons.unshift(res.data.data)
    },
    async updateCoupon(id, data) {
      const res   = await updateCouponApi(id, data)
      const index = this.coupons.findIndex(item => item.id === id)
      if (index !== -1) this.coupons[index] = res.data.data
    },
    async deleteCoupon(id) {
      await deleteCouponApi(id)
      this.coupons = this.coupons.filter(item => item.id !== id)
    },
  },
})