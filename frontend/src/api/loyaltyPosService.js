import http from './api'

// POS-side counterparts to the Mini App's loyalty endpoints — see
// LoyaltyCardController/LoyaltyRedemptionController on the backend.
export const resolveLoyaltyCardApi = (token, branchId) =>
  http.post('/v1/loyalty-card/resolve', { token, branch_id: branchId })

export const showLoyaltyRedemptionApi = code => http.get(`/v1/loyalty-redemptions/${code}`)

export const confirmLoyaltyRedemptionApi = (code, data) =>
  http.post(`/v1/loyalty-redemptions/${code}/confirm`, data)
