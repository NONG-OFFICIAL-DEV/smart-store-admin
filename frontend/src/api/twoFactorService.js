import http from './api'

export const setupTwoFactorApi = () => http.post('/two-factor/setup')
export const confirmTwoFactorApi = code => http.post('/two-factor/confirm', { code })
export const disableTwoFactorApi = () => http.delete('/two-factor')
