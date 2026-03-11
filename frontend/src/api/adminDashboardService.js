import http from './api'

export const getAdminStatsApi       = (params) => http.get('/v1/admin/dashboard/stats',        { params })
export const getAdminChartApi       = (params) => http.get('/v1/admin/dashboard/chart',        { params })
export const getAdminTenantChartApi = (params) => http.get('/v1/admin/dashboard/tenant-chart', { params })
export const getAdminActivityApi    = ()        => http.get('/v1/admin/dashboard/activity')
export const getAdminLiveOrdersApi  = ()        => http.get('/v1/admin/dashboard/live-orders')