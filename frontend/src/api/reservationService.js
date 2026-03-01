import http from './api'

export const getAllReservationsApi  = (filters) => http.get('/reservations', { params: filters })
export const getReservationByIdApi  = (id)      => http.get(`/reservations/${id}`)
export const createReservationApi   = (data)    => http.post('/reservations', data)
export const updateReservationApi   = (id, data)=> http.put(`/reservations/${id}`, data)
export const deleteReservationApi   = (id)      => http.delete(`/reservations/${id}`)