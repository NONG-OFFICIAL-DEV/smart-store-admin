import http from './api'

export const getEmenuSettingsApi = () => http.get('/v1/emenu-settings')
export const updateEmenuSettingsApi = data => http.put('/v1/emenu-settings', data)
