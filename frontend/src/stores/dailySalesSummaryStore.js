import { defineStore } from 'pinia'
import { getAllDailySalesSummariesApi, getDailySalesSummaryByIdApi, createDailySalesSummaryApi, updateDailySalesSummaryApi, deleteDailySalesSummaryApi } from '../api/dailySalesSummaryService'

export const useDailySalesSummaryStore = defineStore('dailySalesSummary', {
  state: () => ({
    dailySalesSummaries: [],
    dailySalesSummary:   null,
    pagination:          {},
  }),

  actions: {
    async fetchDailySalesSummaries(filters) {
      const res                    = await getAllDailySalesSummariesApi(filters)
      this.dailySalesSummaries     = res.data.data.data
      this.pagination              = res.data.data
    },
    async fetchDailySalesSummaryById(id) {
      const res                   = await getDailySalesSummaryByIdApi(id)
      this.dailySalesSummary      = res.data.data
    },
    async createDailySalesSummary(data) {
      const res = await createDailySalesSummaryApi(data)
      this.dailySalesSummaries.unshift(res.data.data)
    },
    async updateDailySalesSummary(id, data) {
      const res   = await updateDailySalesSummaryApi(id, data)
      const index = this.dailySalesSummaries.findIndex(item => item.id === id)
      if (index !== -1) this.dailySalesSummaries[index] = res.data.data
    },
    async deleteDailySalesSummary(id) {
      await deleteDailySalesSummaryApi(id)
      this.dailySalesSummaries = this.dailySalesSummaries.filter(item => item.id !== id)
    },
  },
})