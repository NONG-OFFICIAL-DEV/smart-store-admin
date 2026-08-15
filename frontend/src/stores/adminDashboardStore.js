import { defineStore } from 'pinia'
import { getAdminStatsApi, getAdminChartApi } from '@/api/adminDashboardService'

export const useAdminDashboardStore = defineStore('adminDashboard', {
  state: () => ({
    stats: null, // { kpis, top_tenants, recent_tenants, billing }
    chart: [], // [{ label, mrr, subscriptions }]
    loading: {
      stats: false,
      chart: false
    },
    error: null
  }),

  actions: {
    async fetchAll(period = 'month') {
      await Promise.all([this.fetchStats(period), this.fetchChart(period)])
    },

    async fetchStats(period = 'month') {
      this.loading.stats = true
      this.error = null
      try {
        const res = await getAdminStatsApi({ period })
        this.stats = res.data.data
      } catch (e) {
        this.error = e?.response?.data?.message ?? 'Failed to load stats'
      } finally {
        this.loading.stats = false
      }
    },

    async fetchChart(period = 'month') {
      this.loading.chart = true
      try {
        const res = await getAdminChartApi({ period })
        this.chart = res.data.data
      } catch (e) {
        this.error = e?.response?.data?.message ?? 'Failed to load chart'
      } finally {
        this.loading.chart = false
      }
    }
  }
})
