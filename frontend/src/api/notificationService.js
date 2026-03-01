import http from './api'

export const getAllNotificationsApi     = (filters) => http.get('/notifications', { params: filters })
export const getNotificationByIdApi     = (id)      => http.get(`/notifications/${id}`)
export const markNotificationAsReadApi  = (id)      => http.patch(`/notifications/${id}/read`)
export const markAllNotificationsReadApi= ()         => http.patch('/notifications/read-all')
export const deleteNotificationApi      = (id)      => http.delete(`/notifications/${id}`)