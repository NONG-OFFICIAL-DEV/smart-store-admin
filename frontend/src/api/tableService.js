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
export const deleteTableApi = id => http.delete(`/v1/tables/${id}`)
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
