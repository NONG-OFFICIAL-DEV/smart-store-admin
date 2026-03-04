import { defineStore } from 'pinia'
import {
  getDailySummariesApi,
  getDailySummaryByIdApi
} from '@/api/reportService'

export const useReportStore = defineStore('report', {
  state: () => ({
    summaries: [], // daily_sales_summary rows
    summary: null, // single record
    pagination: {}
  }),

  actions: {
    // Fetch list — filters: { branch_id, date_from, date_to }
    // store.summaries is set directly from res.data.data.data
    async fetchSummaries(filters = {}) {
      const res = await getDailySummariesApi(filters)
      this.summaries = res.data.data.data
      this.pagination = res.data.data
    },

    async fetchSummaryById(id) {
      const res = await getDailySummaryByIdApi(id)
      this.summary = res.data.data
    }
  }
})
