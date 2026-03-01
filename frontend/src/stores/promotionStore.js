import { defineStore } from 'pinia'
import {
  getAllPromotionsApi,
  getPromotionByIdApi,
  createPromotionApi,
  updatePromotionApi,
  deletePromotionApi
} from '../api/promotionService'

export const usePromotionStore = defineStore('promotion', {
  state: () => ({
    promotions: [],
    promotion: null,
    pagination: {}
  }),

  actions: {
    async fetchPromotions(filters) {
      const res = await getAllPromotionsApi(filters)
      this.promotions = res.data.data.data
      this.pagination = res.data.data
    },
    async fetchPromotionById(id) {
      const res = await getPromotionByIdApi(id)
      this.promotion = res.data.data
    },
    async createPromotion(data) {
      const res = await createPromotionApi(data)
      this.promotions.unshift(res.data.data)
    },
    async updatePromotion(id, data) {
      const res = await updatePromotionApi(id, data)
      const index = this.promotions.findIndex(item => item.id === id)
      if (index !== -1) this.promotions[index] = res.data.data
    },
    async deletePromotion(id) {
      await deletePromotionApi(id)
      this.promotions = this.promotions.filter(item => item.id !== id)
    }
  }
})
