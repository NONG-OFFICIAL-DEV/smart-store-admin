import http from './api'

export const getLoyaltySettingsApi = () => http.get('/v1/loyalty-settings')
export const updateLoyaltySettingsApi = data => http.put('/v1/loyalty-settings', data)
