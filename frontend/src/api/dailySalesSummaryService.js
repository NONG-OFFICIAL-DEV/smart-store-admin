import http from './api'

export const getAllDailySalesSummariesApi  = (filters) => http.get('/daily-sales-summaries', { params: filters })
export const getDailySalesSummaryByIdApi   = (id)      => http.get(`/daily-sales-summaries/${id}`)
export const createDailySalesSummaryApi    = (data)    => http.post('/daily-sales-summaries', data)
export const updateDailySalesSummaryApi    = (id, data)=> http.put(`/daily-sales-summaries/${id}`, data)
export const deleteDailySalesSummaryApi    = (id)      => http.delete(`/daily-sales-summaries/${id}`)