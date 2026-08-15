import http from './api'

// Read-only reporting data — no create/update/delete route exists (rows
// are only ever written by DailySalesSummaryService::generate()).
export const getAllDailySalesSummariesApi = filters =>
  http.get('/v1/reports/sales', { params: filters })
export const getDailySalesSummaryForDateApi = date =>
  http.get(`/v1/reports/sales/${date}`)
export const getDailySalesSummaryByBranchApi = (branchId, filters) =>
  http.get(`/v1/branches/${branchId}/sales-summary`, { params: filters })
export const generateDailySalesSummaryApi = (branchId, date) =>
  http.post(`/v1/branches/${branchId}/sales-summary/generate`, { date })
