import http from './api'

export const getAdminStatsApi = params =>
  http.get('/v1/admin/dashboard/stats', { params })
export const getAdminChartApi = params =>
  http.get('/v1/admin/dashboard/chart', { params })
