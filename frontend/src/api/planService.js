import http from './api'

export const getAllPlansApi = filters =>
  http.get('/v1/plans', { params: filters })
export const getPlanByIdApi = id => http.get(`/v1/plans/${id}`)
export const createPlanApi = data => http.post('/v1/plans', data)
export const updatePlanApi = (id, data) => http.put(`/v1/plans/${id}`, data)
export const deletePlanApi = id => http.delete(`/v1/plans/${id}`)
export const togglePlanActiveApi = id => http.patch(`/v1/plans/${id}/toggle-active`)
export const getPlanByTenantApi = (id) => http.get(`/v1/plans/${id}/billing`)

// ── Self-service billing (Tenant Owner) — always acts on the caller's own
// tenant, resolved server-side; never takes a tenant_id from the client. ──
export const getBillingPlansApi = () => http.get('/v1/billing/plans')
export const changePlanApi = data => http.post('/v1/billing/change-plan', data)
export const renewSubscriptionApi = () => http.post('/v1/billing/renew')