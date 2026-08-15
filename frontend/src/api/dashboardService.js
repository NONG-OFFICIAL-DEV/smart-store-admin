import http from './api'

export const getDashboardStatsApi = (params) => http.get('/v1/dashboard/stats', { params })
export const getDashboardChartApi = (params) => http.get('/v1/dashboard/chart', { params })
export const getDashboardTopProductsApi = (params) => http.get('/v1/dashboard/top-products', { params })
