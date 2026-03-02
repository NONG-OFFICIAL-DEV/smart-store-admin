import http from './api'

export const getAllReservationsApi = filters =>
  http.get('/v1/reservations', { params: filters })
export const getReservationByIdApi = id => http.get(`/v1/reservations/${id}`)
export const createReservationApi = data => http.post('/v1/reservations', data)
export const updateReservationApi = (id, data) =>
  http.put(`/v1/reservations/${id}`, data)
export const deleteReservationApi = id => http.delete(`/v1/reservations/${id}`)
