import http from './api'

export const getAllNotificationsApi = filters =>
  http.get('/v1/notifications', { params: filters })
export const getUnreadNotificationCountApi = () =>
  http.get('/v1/notifications/unread-count')
export const getNotificationByIdApi = id => http.get(`/v1/notifications/${id}`)
export const markNotificationAsReadApi = id =>
  http.patch(`/v1/notifications/${id}/read`)
export const markAllNotificationsReadApi = () =>
  http.patch('/v1/notifications/read-all')
export const deleteNotificationApi = id =>
  http.delete(`/v1/notifications/${id}`)

export const getNotificationPreferencesApi = () =>
  http.get('/v1/notifications/preferences')
export const updateNotificationPreferencesApi = data =>
  http.patch('/v1/notifications/preferences', data)
export const getTelegramLinkUrlApi = () =>
  http.post('/v1/notifications/telegram/link')
export const unlinkTelegramApi = () =>
  http.post('/v1/notifications/telegram/unlink')
