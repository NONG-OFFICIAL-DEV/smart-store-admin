import http from './api'

export const getTelegramSettingsApi = () => http.get('/v1/telegram-settings')
export const updateTelegramSettingsApi = data => http.put('/v1/telegram-settings', data)
export const testTelegramConnectionApi = () => http.post('/v1/telegram-settings/test')
