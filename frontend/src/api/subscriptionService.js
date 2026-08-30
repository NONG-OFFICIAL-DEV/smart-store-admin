import http from './api'

// Assigning/changing a tenant's plan always goes through store() — there is
// no update() route; the backend cancels whatever's active (if anything)
// and creates a fresh row for the new plan. Plan history and payment
// history are read via planService.js's getPlanByTenantApi (plans/{tenant}
// /billing already returns both, so TenantSubscriptionPage.vue doesn't
// need a separate report-side call for them).
export const createSubscriptionApi = data => http.post('/v1/subscriptions', data)
export const deleteSubscriptionApi = id => http.delete(`/v1/subscriptions/${id}`)
export const toggleSubscriptionActiveApi = id => http.patch(`/v1/subscriptions/${id}/toggle-active`)
export const cancelSubscriptionApi = id => http.patch(`/v1/subscriptions/${id}/cancel`)
export const renewSubscriptionApi = id => http.patch(`/v1/subscriptions/${id}/renew`)

// Manual payment reconciliation ledger — records a payment already received
// against a tenant's active subscription as a real Invoice.
export const recordTenantPaymentApi = (tenantId, data) =>
  http.post(`/v1/tenants/${tenantId}/payments`, data)