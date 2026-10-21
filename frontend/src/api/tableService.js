import http from './api'

export const getAllTablesApi = filters =>
  http.get('/v1/tables', { params: filters })
export const getTableByIdApi = id => http.get(`/v1/tables/${id}`)
export const createTableApi = data => http.post('/v1/tables', data)
export const updateTableApi = (id, data) => http.put(`/v1/tables/${id}`, data)
export const updateTableStatusApi = (id, status) =>
  http.patch(`/v1/tables/${id}/status`, { status })
export const getActiveOrderByTableApi = id =>
  http.get(`/v1/tables/${id}/active-order`)
// A table can carry more than one separate open order per sitting (repeat
// e-menu order, or a second POS round) — this returns every one of them.
export const getOrdersByTableApi = id => http.get(`/v1/tables/${id}/orders`)
// The session-merged bill for a table's current running tab — items merged
// by product + variant + unit price + modifier set (see
// TableSessionService::getSessionBill()), so printing it never shows the
// same product as two separate duplicate lines the way summing raw
// per-order items would.
export const getTableBillApi = id => http.get(`/v1/tables/${id}/bill`)
export const deleteTableApi = id => http.delete(`/v1/tables/${id}`)
// Drag-reorder in TableManagement.vue — assigns sequential sort_order
// matching the given id order (see TableController::reorder()).
export const reorderTablesApi = tableIds =>
  http.post('/v1/tables/reorder', { table_ids: tableIds })

// Pays off every unpaid order in a table session at once and closes it —
// the Table modal's PAY button always sends a session id here, never a
// single order id (see TableSessionService::paySession() on the backend).
export const paySessionApi = (sessionId, data) =>
  http.patch(`/v1/table-sessions/${sessionId}/pay`, data)
// Get QR code info
export const getQrCode = id => {
  return http.get(`v1/tables/${id}/qr-code`)
}

// Regenerate QR
export const regenerateQrCode = id => {
  return http.post(`v1/tables/${id}/qr-code/regenerate`)
}

// Download QR (returns blob)
export const downloadQrCode = id => {
  return http.get(`v1/tables/${id}/qr-code/download`, {
    responseType: 'blob'
  })
}
