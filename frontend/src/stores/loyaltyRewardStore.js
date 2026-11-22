import { defineStore } from 'pinia'
import {
  getAllLoyaltyRewardsApi,
  getLoyaltyRewardByIdApi,
  createLoyaltyRewardApi,
  updateLoyaltyRewardApi,
  deleteLoyaltyRewardApi
} from '../api/loyaltyRewardService'

export const useLoyaltyRewardStore = defineStore('loyaltyReward', {
  state: () => ({
    rewards: [],
    reward: null,
    pagination: {}
  }),

  actions: {
    async fetchRewards(filters) {
      const res = await getAllLoyaltyRewardsApi(filters)
      this.rewards = res.data.data
      this.pagination = res.data.meta
    },
    async fetchRewardById(id) {
      const res = await getLoyaltyRewardByIdApi(id)
      this.reward = res.data.data
    },
    async createReward(data) {
      const res = await createLoyaltyRewardApi(data)
      this.rewards.unshift(res.data.data)
    },
    async updateReward(id, data) {
      const res = await updateLoyaltyRewardApi(id, data)
      const index = this.rewards.findIndex(item => item.id === id)
      if (index !== -1) this.rewards[index] = res.data.data
    },
    async deleteReward(id) {
      await deleteLoyaltyRewardApi(id)
      this.rewards = this.rewards.filter(item => item.id !== id)
    }
  }
})
