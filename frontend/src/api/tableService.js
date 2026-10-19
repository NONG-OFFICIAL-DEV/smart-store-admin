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
export const deleteTableApi = id => http.delete(`/v1/tables/${id}`)

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
