import http from './api'

export const getAllActivityLogsApi = filters =>
  http.get('/v1/activity-logs', { params: filters })
export const getActivityLogByIdApi = id => http.get(`/v1/activity-logs/${id}`)
