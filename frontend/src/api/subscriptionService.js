import http from './api'

// Assigning/changing a tenant's plan always goes through store() — there is
// no update() route; the backend cancels whatever's active (if anything)
// and creates a fresh row for the new plan.
export const getAllsubscriptionsApi = filters =>
  http.get('/v1/subscriptions', { params: filters })
export const getSubscriptionByIdApi = id => http.get(`/v1/subscriptions/${id}`)
export const createSubscriptionApi = data => http.post('/v1/subscriptions', data)
export const deleteSubscriptionApi = id => http.delete(`/v1/subscriptions/${id}`)
export const toggleSubscriptionActiveApi = id => http.patch(`/v1/subscriptions/${id}/toggle-active`)
export const cancelSubscriptionApi = id => http.patch(`/v1/subscriptions/${id}/cancel`)
export const renewSubscriptionApi = id => http.patch(`/v1/subscriptions/${id}/renew`)

// Report side — separate from the action endpoints above. tenant_id is
// required by the backend (this is a per-tenant drill-down, not a global feed).
export const getSubscriptionPlanHistoryApi = filters =>
  http.get('/v1/subscription-plan-history', { params: filters })