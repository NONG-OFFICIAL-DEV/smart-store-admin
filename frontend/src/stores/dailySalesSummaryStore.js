import { defineStore } from 'pinia'
import { getAllDailySalesSummariesApi, getDailySalesSummaryForDateApi, getDailySalesSummaryByBranchApi, generateDailySalesSummaryApi } from '../api/dailySalesSummaryService'

export const useDailySalesSummaryStore = defineStore('dailySalesSummary', {
  state: () => ({
    dailySalesSummaries: [],
    dailySalesSummary:   [],
    pagination:          {},
  }),

  actions: {
    async fetchDailySalesSummaries(filters) {
      const res                    = await getAllDailySalesSummariesApi(filters)
      this.dailySalesSummaries     = res.data.data
      this.pagination              = res.data.meta
    },
    async fetchDailySalesSummaryForDate(date) {
      const res              = await getDailySalesSummaryForDateApi(date)
      this.dailySalesSummary = res.data.data
    },
    async fetchDailySalesSummaryByBranch(branchId, filters) {
      const res                    = await getDailySalesSummaryByBranchApi(branchId, filters)
      this.dailySalesSummaries     = res.data.data
      this.pagination              = res.data.meta
    },
    async generateDailySalesSummary(branchId, date) {
      const res = await generateDailySalesSummaryApi(branchId, date)
      this.dailySalesSummaries.unshift(res.data.data)
    },
  },
})
