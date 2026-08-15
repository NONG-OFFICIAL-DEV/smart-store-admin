import http from './api'

// GET /api/v1/daily-sales-summaries?branch_id=&date_from=&date_to=
export const getDailySummariesApi = params =>
  http.get('/v1/reports/sales', { params })

// GET /api/v1/v1/reports/:id
export const getDailySummaryByIdApi = id => http.get(`/v1/reports/${id}`)
