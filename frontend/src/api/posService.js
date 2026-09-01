import http from './api'

// ── Retail (mart) POS — existing, already-working checkout endpoints ───────
export const getRetailPosProductsApi = params =>
  http.get('/v1/mart/pos/products', { params })
export const getRetailPosCategoriesApi = params =>
  http.get('/v1/mart/pos/categories', { params })
export const submitRetailOrderApi = payload =>
  http.post('/v1/mart/pos/customer-orders', payload)

// ── Food (hospitality/coffee) POS — existing, already-working endpoints ────
export const getFoodPosProductsApi = params =>
  http.get('/v1/hospitality/pos/products', { params })
export const submitFoodOrderApi = payload =>
  http.post('/v1/coffee/pos/orders', payload)
