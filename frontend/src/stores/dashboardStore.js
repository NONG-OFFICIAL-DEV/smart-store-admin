import { defineStore } from 'pinia'
import {
  getDashboardStatsApi,
  getDashboardChartApi,
  getDashboardTopProductsApi,
} from '@/api/dashboardService'

export const useDashboardStore = defineStore('dashboard', {
  state: () => ({
    stats:       null,
    chart:       [],
    liveOrders:  [],
    topProducts: [],
    activity:    [],
    loading: {
      stats:       false,
      chart:       false,
      liveOrders:  false,
      topProducts: false,
      activity:    false,
    },
    error: null,
  }),

  actions: {
    async fetchAll(period = 'week') {
      await Promise.all([
        this.fetchStats(period),
        this.fetchChart(period),
        this.fetchTopProducts(period)
      ])
    },

    async fetchStats(period) {
      this.loading.stats = true
      try {
        const res = await getDashboardStatsApi({ period })
        this.stats = res.data.data
      } catch (e) {
        this.error = e?.response?.data?.message ?? 'Failed to load stats'
      } finally {
        this.loading.stats = false
      }
    },

    async fetchChart(period) {
      this.loading.chart = true
      try {
        const res = await getDashboardChartApi({ period })
        this.chart = res.data.data
      } finally {
        this.loading.chart = false
      }
    },

    async fetchTopProducts(period) {
      this.loading.topProducts = true
      try {
        const res = await getDashboardTopProductsApi({ period })
        this.topProducts = res.data.data
      } finally {
        this.loading.topProducts = false
      }
    },
  },
})