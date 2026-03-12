import { defineStore } from 'pinia'
import {
  getAdminStatsApi,
  getAdminChartApi,
  getAdminTenantChartApi,
  getAdminActivityApi,
} from '@/api/adminDashboardService'

export const useAdminDashboardStore = defineStore('adminDashboard', {
  state: () => ({
    stats:       null,
    chart:       [],
    tenantChart: [],
    activity:    [],
    liveOrders:  [],
    loading: {
      stats:       false,
      chart:       false,
      tenantChart: false,
      activity:    false,
      liveOrders:  false,
    },
    error: null,
  }),

  actions: {
    async fetchAll(period = 'week') {
      await Promise.all([
        this.fetchStats(period),
        this.fetchChart(period),
        this.fetchTenantChart(period),
        this.fetchActivity(),
      ])
    },

    async fetchStats(period) {
      this.loading.stats = true
      try {
        const res  = await getAdminStatsApi({ period })
        this.stats = res.data.data
      } catch (e) {
        this.error = e?.response?.data?.message ?? 'Failed'
      } finally {
        this.loading.stats = false
      }
    },

    async fetchChart(period) {
      this.loading.chart = true
      try {
        const res  = await getAdminChartApi({ period })
        this.chart = res.data.data
      } finally {
        this.loading.chart = false
      }
    },

    async fetchTenantChart(period) {
      this.loading.tenantChart = true
      try {
        const res        = await getAdminTenantChartApi({ period })
        this.tenantChart = res.data.data
      } finally {
        this.loading.tenantChart = false
      }
    },

    async fetchActivity() {
      this.loading.activity = true
      try {
        const res      = await getAdminActivityApi()
        this.activity  = res.data.data
      } finally {
        this.loading.activity = false
      }
    },
  },
})